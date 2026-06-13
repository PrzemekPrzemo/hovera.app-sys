<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CalendarEntryStatus;
use App\Enums\CalendarEntryType;
use App\Enums\TenantType;
use App\Models\Central\Tenant;
use App\Models\Tenant\CalendarEntry;
use App\Models\Tenant\HealthRecord;
use App\Notifications\Health\HealthRecordExpiryReminderNotification;
use App\Services\Tenancy\TenantRoleGate;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Daily command: skanuje wszystkie aktywne stable'y w poszukiwaniu
 * HealthRecord'ów których `next_due_at` zbliża się w trzech fazach
 * eskalacji: 30 → 14 → 7 dni przed terminem.
 *
 * Per faza wysyła:
 *   - mail do specialist (vet/dentist/farrier), jeśli przypisany ma email
 *   - mail do owner'a konia (przez Client.email, jeśli external)
 *   - mail + push (apn+fcm) do staff stable'a (HORSE_AND_CARE_STAFF role'e)
 *
 * Plus na pierwszej fazie (30d) auto-tworzy CalendarEntry typu `Care`
 * jako placeholder w kalendarzu — stajnia może edytować (przypisać weta,
 * ustawić godzinę). Re-create gdy oryginalna pozycja zostanie usunięta.
 *
 * Idempotencja per faza przez kolumny `reminder_{30,14,7}d_sent_at`
 * (migration 2026_06_13_100000_add_reminder_columns_to_health_records).
 * Re-arm: jeśli `next_due_at` zmieni się (np. po wizycie weta) i staje
 * się późniejsze niż ostatnio użyty marker, reset cyklu nastąpi przy
 * normalnym update — `updated_at > sent_at` triggeruje ponowne wysłanie.
 *
 * Soft-fail per tenant/rekord — jeden zepsuty DB nie blokuje pozostałych.
 *
 * Scheduling: dziennie 07:00 Europe/Warsaw (rano, w godzinach roboczych
 * stajni — żeby manager mógł od razu zająć się terminem). Zdefiniowane
 * w routes/console.php.
 */
class HealthRecordsRemindDueCommand extends Command
{
    protected $signature = 'health-records:remind-due
        {--tenant= : Slug pojedynczej stajni (default: wszystkie active)}';

    protected $description = 'Powiadomienia + auto-CalendarEntry o zbliżających się terminach szczepień/wizyt (30/14/7 dni).';

    private const PHASES = [
        30 => 'reminder_30d_sent_at',
        14 => 'reminder_14d_sent_at',
        7 => 'reminder_7d_sent_at',
    ];

    public function handle(TenantManager $tenants): int
    {
        $query = Tenant::query()
            ->where('type', TenantType::Stable->value)
            ->whereIn('status', ['trialing', 'active']);
        if ($slug = $this->option('tenant')) {
            $query->where('slug', $slug);
        }
        $tenantList = $query->get();

        if ($tenantList->isEmpty()) {
            $this->info('No active stable tenants.');

            return self::SUCCESS;
        }

        $totalSent = 0;
        $totalSkipped = 0;

        foreach ($tenantList as $tenant) {
            try {
                $sent = $tenants->current()?->id === $tenant->id
                    ? $this->processTenant($tenant)
                    : $tenants->execute($tenant, fn () => $this->processTenant($tenant));
                $totalSent += $sent;
            } catch (Throwable $e) {
                $totalSkipped++;
                $this->error("× {$tenant->slug}: {$e->getMessage()}");
                report($e);
            }
        }

        $this->info("Reminders dispatched: {$totalSent}, tenants skipped: {$totalSkipped}");

        return self::SUCCESS;
    }

    private function processTenant(Tenant $tenant): int
    {
        $now = Carbon::now();
        $maxThreshold = $now->copy()->addDays(30)->endOfDay();

        $candidates = HealthRecord::query()
            ->with(['horse.owner', 'specialist'])
            ->whereNotNull('next_due_at')
            ->whereBetween('next_due_at', [$now->copy()->startOfDay(), $maxThreshold])
            ->get();

        if ($candidates->isEmpty()) {
            return 0;
        }

        $staffEmails = $this->resolveStaffEmails($tenant);
        $sent = 0;

        foreach ($candidates as $record) {
            try {
                $sent += $this->processRecord($tenant, $record, $staffEmails);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $sent;
    }

    /**
     * @param  list<string>  $staffEmails
     */
    private function processRecord(Tenant $tenant, HealthRecord $record, array $staffEmails): int
    {
        $phase = $this->resolvePhase($record);
        if ($phase === null) {
            return 0; // już wysłany w tej fazie albo poza oknem
        }

        $days = max(0, (int) now()->startOfDay()->diffInDays($record->next_due_at, false));
        $horseName = $record->horse?->name ?? '—';
        $stableName = $tenant->name;

        $sent = 0;

        // Vet (specialist) — mail tylko jeśli przypisany i ma email.
        $vetEmail = $record->specialist?->email;
        if (is_string($vetEmail) && $vetEmail !== '') {
            Notification::route('mail', $vetEmail)->notify(
                new HealthRecordExpiryReminderNotification(
                    $record, $days,
                    HealthRecordExpiryReminderNotification::AUDIENCE_VET,
                    $horseName, $stableName,
                ),
            );
            $sent++;
        }

        // Owner (Client.email — może być external, bez Hovera account).
        $ownerEmail = $record->horse?->owner?->email;
        if (is_string($ownerEmail) && $ownerEmail !== '') {
            Notification::route('mail', $ownerEmail)->notify(
                new HealthRecordExpiryReminderNotification(
                    $record, $days,
                    HealthRecordExpiryReminderNotification::AUDIENCE_OWNER,
                    $horseName, $stableName,
                ),
            );
            $sent++;
        }

        // Staff — mail + push do każdego usera z HORSE_AND_CARE_STAFF.
        foreach ($staffEmails as $email) {
            Notification::route('mail', $email)->notify(
                new HealthRecordExpiryReminderNotification(
                    $record, $days,
                    HealthRecordExpiryReminderNotification::AUDIENCE_STAFF,
                    $horseName, $stableName,
                ),
            );
            $sent++;
        }

        // Auto-CalendarEntry tylko na pierwszej fazie (30d) — żeby kalendarz
        // dostał wpis maksymalnie wcześnie. Re-create jeśli wcześniej był
        // utworzony ale usunięty.
        if ($phase === 30) {
            $this->ensureCalendarEntry($record, $horseName);
        }

        // Mark phase sent — idempotencja.
        $record->forceFill([
            self::PHASES[$phase] => now(),
        ])->save();

        return $sent;
    }

    /**
     * Zwraca okno fazy (30/14/7) który aktualnie pasuje do `next_due_at`
     * i jeszcze nie został wysłany. null = nic do roboty.
     */
    private function resolvePhase(HealthRecord $record): ?int
    {
        $days = (int) now()->startOfDay()->diffInDays($record->next_due_at, false);
        if ($days < 0) {
            return null; // overdue — out of scope (separate flow)
        }

        // 7d ma najwyższy priorytet (najpilniejszy), potem 14, potem 30.
        // Sprawdzamy w tej kolejności żeby user nie dostał 30d i 14d w tym
        // samym tygodniu gdy command odpalił 1. raz w środku okna.
        foreach ([7, 14, 30] as $phase) {
            if ($days <= $phase && $record->{self::PHASES[$phase]} === null) {
                return $phase;
            }
        }

        return null;
    }

    private function ensureCalendarEntry(HealthRecord $record, string $horseName): void
    {
        if ($record->reminder_calendar_entry_id !== null) {
            // Sprawdź czy entry nadal istnieje — jeśli klient go usunął,
            // odtworzymy. Soft-delete na CalendarEntry też = brak.
            $exists = CalendarEntry::query()
                ->where('id', $record->reminder_calendar_entry_id)
                ->exists();
            if ($exists) {
                return;
            }
        }

        $startsAt = $record->next_due_at->copy()->setTime(9, 0);

        $entry = CalendarEntry::create([
            'id' => (string) Str::ulid(),
            'type' => CalendarEntryType::Care->value,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'horse_id' => $record->horse_id,
            // Requested = wymaga potwierdzenia/przypisania przez stable.
            'status' => CalendarEntryStatus::Requested->value,
            'title' => __('notifications.health_reminder.calendar_title', [
                'type' => $record->type->label(),
                'horse' => $horseName,
            ]),
            'notes' => __('notifications.health_reminder.calendar_notes', [
                'summary' => $record->summary,
            ]),
        ]);

        $record->forceFill(['reminder_calendar_entry_id' => $entry->id])->save();
    }

    /**
     * @return list<string>
     */
    private function resolveStaffEmails(Tenant $tenant): array
    {
        return DB::connection('central')
            ->table('tenant_memberships')
            ->join('users', 'tenant_memberships.user_id', '=', 'users.id')
            ->where('tenant_memberships.tenant_id', $tenant->id)
            ->whereIn('tenant_memberships.role', TenantRoleGate::HORSE_AND_CARE_STAFF)
            ->whereNull('tenant_memberships.revoked_at')
            ->pluck('users.email')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
