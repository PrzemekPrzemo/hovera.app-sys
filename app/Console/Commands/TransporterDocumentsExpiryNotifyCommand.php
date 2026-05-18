<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Transport\Notifications\TransporterDocumentExpiringSoonNotification;
use App\Enums\TenantType;
use App\Enums\TransporterDocumentType;
use App\Models\Central\Tenant;
use App\Models\Tenant\TransporterDocument;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Wysyła mail do owner'a transportera 30 dni przed wygaśnięciem dowolnego
 * wymaganego dokumentu PWL.
 *
 * Idempotencja: kolumna `expiry_notified_at` na transporter_documents
 * (migration: 2026_05_18_141000_add_expiry_notified_at_to_transporter_documents).
 * Re-upload resetuje notify (DocumentUploadService wpisuje nowy `expires_at`
 * i nie kopiuje `expiry_notified_at` w forceFill — pozostaje null dla
 * nowego rekordu, a w przypadku update odświeżamy reset poniżej).
 *
 * Per-tenant iteration: skipuje tenant'y bez aktywnej subskrypcji + bez
 * `type=transporter`. Jeden zepsuty DB nie blokuje pozostałych.
 *
 * Scheduling: dziennie 04:00 (zaraz po snapshot health). Ustalone tutaj
 * a nie w routes/console.php żeby logika cron'a była przy command'zie.
 *
 * Patrz docs/TRANSPORT.md §1 (onboarding — expiry watchdog).
 */
class TransporterDocumentsExpiryNotifyCommand extends Command
{
    protected $signature = 'transporter:docs-expiry-notify
        {--tenant= : Slug pojedynczego transportera (default: wszyscy active)}
        {--days-ahead=30 : Ile dni przed wygaśnięciem powiadomić}';

    protected $description = 'Wysyła mail do transporterów 30 dni przed wygaśnięciem wymaganych dokumentów PWL.';

    public function handle(TenantManager $tenants): int
    {
        $daysAhead = (int) $this->option('days-ahead');

        $query = Tenant::query()
            ->where('type', TenantType::Transporter->value)
            ->whereIn('status', ['trialing', 'active']);
        if ($slug = $this->option('tenant')) {
            $query->where('slug', $slug);
        }

        $tenantList = $query->get();
        if ($tenantList->isEmpty()) {
            $this->info('No active transporters.');

            return self::SUCCESS;
        }

        $totalSent = 0;
        $totalSkipped = 0;

        foreach ($tenantList as $tenant) {
            try {
                if ($tenants->current()?->id === $tenant->id) {
                    $sent = $this->processTenant($tenant, $daysAhead);
                } else {
                    $sent = $tenants->execute(
                        $tenant,
                        fn () => $this->processTenant($tenant, $daysAhead),
                    );
                }
                $totalSent += $sent;
            } catch (\Throwable $e) {
                $totalSkipped++;
                $this->error("× {$tenant->slug}: {$e->getMessage()}");
                report($e);
            }
        }

        $this->info("Expiry notifications sent: {$totalSent}, tenants skipped: {$totalSkipped}");

        return self::SUCCESS;
    }

    private function processTenant(Tenant $tenant, int $daysAhead): int
    {
        $now = Carbon::now();
        $threshold = $now->copy()->addDays($daysAhead)->endOfDay();

        // PWL required + KRS — pozostałe (legacy, opcjonalne) ignorujemy
        // żeby nie spamować skrzynki transportera mailami o nieistotnych dokumentach.
        $watchedTypes = collect(TransporterDocumentType::pwlRequiredCases())
            ->map(fn (TransporterDocumentType $t) => $t->value)
            ->push(TransporterDocumentType::CompanyRegistration->value)
            ->all();

        // Bierzemy tylko verified — pending/rejected dokument nie ma sensu
        // przypominać że wygasa (user już je zarządza).
        $candidates = TransporterDocument::query()
            ->where('status', TransporterDocument::STATUS_VERIFIED)
            ->whereIn('document_type', $watchedTypes)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $threshold)
            ->where('expires_at', '>=', $now->copy()->startOfDay())
            ->where(function ($q) {
                $q->whereNull('expiry_notified_at')
                    // Re-arm: jeśli `expires_at` zostało zmienione (np. upload nowej
                    // wersji) po dacie notify — wysyłamy ponownie.
                    ->orWhereColumn('expiry_notified_at', '<', 'updated_at');
            })
            ->get();

        if ($candidates->isEmpty()) {
            return 0;
        }

        $ownerEmail = $this->resolveOwnerEmail($tenant);
        if (! $ownerEmail) {
            return 0;
        }

        $sent = 0;
        foreach ($candidates as $doc) {
            $days = max(0, $now->copy()->startOfDay()->diffInDays($doc->expires_at, false));
            try {
                Notification::route('mail', $ownerEmail)->notify(
                    new TransporterDocumentExpiringSoonNotification($tenant, $doc, (int) $days),
                );
                $doc->forceFill(['expiry_notified_at' => $now])->save();
                $sent++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $sent;
    }

    private function resolveOwnerEmail(Tenant $tenant): ?string
    {
        return DB::connection('central')
            ->table('tenant_memberships')
            ->join('users', 'tenant_memberships.user_id', '=', 'users.id')
            ->where('tenant_memberships.tenant_id', $tenant->id)
            ->where('tenant_memberships.role', 'owner')
            ->whereNull('tenant_memberships.revoked_at')
            ->value('users.email');
    }
}
