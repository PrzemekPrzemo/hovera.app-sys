<?php

declare(strict_types=1);

namespace App\Jobs\Owner;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use App\Models\Tenant\Client;
use App\Models\Tenant\Horse;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoiceItem;
use App\Tenancy\TenantManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Faza 3 PR 3.2 Owner ↔ Stable shared view — auto-billing job dla
 * miesięcznego pensjonatu. Patrz docs/OWNER-STABLE-ROADMAP.md.
 *
 * Uruchamiany schedulerem 1. dnia każdego miesiąca o 02:00. Iteruje
 * wszystkie aktywne HorseBoardingAssignment'y i dla każdego:
 *   1. Switch'uje na DB stajni (TenantManager::execute)
 *   2. Znajduje Client matching central_user_id = owner_user_id
 *   3. Sprawdza czy jest już draft invoice dla okresu (idempotent)
 *   4. Buduje items z:
 *      - Box monthly_rate_cents (gdy horse.box_id ustawiony)
 *      - Każdy aktywny horse_boarding_services z frequency=monthly
 *   5. Tworzy Invoice (kind=fv, status=draft) + items + recompute totals
 *
 * Operator stajni zostaje z draft'em do review'u (zmodyfikuj, dodaj
 * one-time charges, kliknij "Wystaw" w QuoteResource → KSeF etc.).
 * Owner zobaczy fakturę dopiero po issue (status != draft).
 *
 * Idempotency: skip gdy istnieje invoice z metadata.billing_period
 * matching obecny okres + metadata.boarding_assignment_id matching
 * tego assignment. Bezpieczne dla powtórzonego runu (np. failed scheduler
 * + manual rerun).
 *
 * Edge cases (każdy = soft skip + log):
 *   - Client nie istnieje (owner jeszcze nie zlinkowany) → skip
 *   - Horse nie istnieje (sync rift) → skip
 *   - Brak items (no box, no services) → skip (no invoice created)
 *   - Service ended_at < period start albo starts_at > period end → skip item
 */
class GenerateMonthlyBoardingInvoicesJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    /**
     * Period to bill — domyślnie poprzedni miesiąc (job uruchomiony
     * 1 czerwca billuje maj). Można nadpisać dla backfill'u / testów.
     */
    public ?string $periodIso = null;

    public function __construct(?Carbon $periodStart = null)
    {
        // Default: pierwszy dzień poprzedniego miesiąca. Job leci 1. dnia
        // miesiąca o 02:00 — billujemy zamknięty już okres.
        $effective = $periodStart ?? now()->subMonthNoOverflow()->startOfMonth();
        $this->periodIso = $effective->format('Y-m');
    }

    /**
     * Unique lock key — zapobiega podwójnemu uruchomieniu na ten sam okres.
     * Per environment, expire po godzinie (bezpieczny default jeśli job
     * crashnie bez release locka).
     */
    public function uniqueId(): string
    {
        return 'autobilling:'.$this->periodIso;
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(TenantManager $tenants): void
    {
        $period = Carbon::createFromFormat('Y-m-d', $this->periodIso.'-01')->startOfMonth();
        Log::info('autobilling: start', ['period' => $this->periodIso]);

        $totalAssignments = 0;
        $invoicesCreated = 0;
        $skipped = 0;

        // Chunk po 100 — owner panel docelowo może mieć tysiące assignment'ów.
        HorseBoardingAssignment::query()
            ->where('status', HorseBoardingAssignment::STATUS_ACTIVE)
            ->orderBy('id')
            ->chunk(100, function ($assignments) use ($tenants, $period, &$totalAssignments, &$invoicesCreated, &$skipped) {
                foreach ($assignments as $assignment) {
                    $totalAssignments++;
                    try {
                        $result = $this->billAssignment($tenants, $assignment, $period);
                        if ($result) {
                            $invoicesCreated++;
                        } else {
                            $skipped++;
                        }
                    } catch (Throwable $e) {
                        // Soft fail per assignment — log i lecimy dalej, nie
                        // crash'ujemy całego runu dla 1 broken stable'a.
                        $skipped++;
                        Log::warning('autobilling: assignment failed', [
                            'assignment_id' => $assignment->id,
                            'stable_tenant_id' => $assignment->stable_tenant_id,
                            'error' => $e->getMessage(),
                        ]);
                        report($e);
                    }
                }
            });

        Log::info('autobilling: done', [
            'period' => $this->periodIso,
            'total' => $totalAssignments,
            'created' => $invoicesCreated,
            'skipped' => $skipped,
        ]);
    }

    /**
     * Procesuje pojedynczy assignment — szwitch na stable, generuje
     * invoice. Zwraca true jeśli stworzony, false jeśli skip
     * (idempotency / no items / missing client/horse).
     */
    private function billAssignment(
        TenantManager $tenants,
        HorseBoardingAssignment $assignment,
        Carbon $period,
    ): bool {
        $stableTenant = Tenant::query()->find($assignment->stable_tenant_id);
        if ($stableTenant === null) {
            Log::warning('autobilling: stable tenant missing', [
                'assignment_id' => $assignment->id,
                'stable_tenant_id' => $assignment->stable_tenant_id,
            ]);

            return false;
        }

        return (bool) $tenants->execute($stableTenant, function () use ($assignment, $period, $stableTenant) {
            // Krok 1: znajdź Client matching owner user_id.
            $client = Client::query()
                ->where('central_user_id', $assignment->owner_user_id)
                ->first();

            if ($client === null) {
                Log::info('autobilling: skip — no client link', [
                    'assignment_id' => $assignment->id,
                    'owner_user_id' => $assignment->owner_user_id,
                    'stable_tenant_id' => $stableTenant->id,
                ]);

                return false;
            }

            // Krok 2: znajdź horse z eager load box + services.
            $horse = Horse::query()
                ->where('central_horse_id', $assignment->central_horse_id)
                ->with([
                    'box',
                    'boardingServices' => fn ($q) => $q->withPivot(['price_override_cents', 'quantity', 'starts_at', 'ends_at']),
                ])
                ->first();

            if ($horse === null) {
                Log::info('autobilling: skip — horse missing in stable', [
                    'assignment_id' => $assignment->id,
                    'central_horse_id' => $assignment->central_horse_id,
                ]);

                return false;
            }

            // Krok 3: idempotency — czy już istnieje invoice dla tego
            // assignment + period? Szukamy po metadata (json_extract dla
            // SQLite + MySQL JSON_EXTRACT).
            $existing = Invoice::query()
                ->where('client_id', $client->id)
                ->whereRaw("json_extract(metadata, '$.billing_period') = ?", [$this->periodIso])
                ->whereRaw("json_extract(metadata, '$.boarding_assignment_id') = ?", [$assignment->id])
                ->exists();

            if ($existing) {
                Log::info('autobilling: skip — invoice already exists', [
                    'assignment_id' => $assignment->id,
                    'period' => $this->periodIso,
                ]);

                return false;
            }

            // Krok 4: zbuduj items
            $items = $this->buildItems($horse, $assignment->central_horse_id, $period);

            if ($items === []) {
                Log::info('autobilling: skip — no billable items', [
                    'assignment_id' => $assignment->id,
                    'horse_id' => $horse->id,
                ]);

                return false;
            }

            // Krok 5: stwórz Invoice + items
            $this->createInvoice($client, $horse, $assignment, $period, $items, $stableTenant);

            return true;
        });
    }

    /**
     * Buduje listę itemów do faktury: box + active monthly services.
     * Filtruje services po starts_at/ends_at vs period.
     *
     * @return list<array{name: string, unit: string, unit_price_cents: int, quantity: float, vat_rate: string}>
     */
    private function buildItems(Horse $horse, string $centralHorseId, Carbon $period): array
    {
        $items = [];
        $periodEnd = $period->copy()->endOfMonth();

        // Box (jeśli horse aktualnie przypisany do boxa z monthly_rate).
        if ($horse->box !== null && $horse->box->monthly_rate_cents !== null && (int) $horse->box->monthly_rate_cents > 0) {
            $items[] = [
                'name' => __('owner/invoices.autobilling.line.box', [
                    'box' => $horse->box->name,
                    'horse' => $horse->name,
                ]),
                'unit' => 'm-c',
                'unit_price_cents' => (int) $horse->box->monthly_rate_cents,
                'quantity' => 1,
                'vat_rate' => '23',
            ];
        }

        // Active monthly services — filter po datach pivot'u i frequency.
        foreach ($horse->boardingServices as $service) {
            // Frequency != monthly = skip (per-use / daily / weekly liczone
            // przez stajnię ręcznie albo w innym job'ie — daily auto-billing
            // to scope dla osobnej iteracji).
            if ($service->frequency->value !== 'monthly') {
                continue;
            }
            $pivot = $service->pivot;
            $startsAt = $pivot->starts_at !== null ? Carbon::parse((string) $pivot->starts_at) : null;
            $endsAt = $pivot->ends_at !== null ? Carbon::parse((string) $pivot->ends_at) : null;

            // Service ended przed okresem — skip.
            if ($endsAt !== null && $endsAt->lt($period)) {
                continue;
            }
            // Service starts dopiero po okresie — skip.
            if ($startsAt !== null && $startsAt->gt($periodEnd)) {
                continue;
            }

            $effectivePrice = $pivot->price_override_cents !== null
                ? (int) $pivot->price_override_cents
                : (int) $service->price_cents;
            $qty = (float) ($pivot->quantity ?? 1);

            if ($effectivePrice <= 0 || $qty <= 0) {
                continue;
            }

            $items[] = [
                'name' => $service->name.' — '.$horse->name,
                'unit' => (string) $service->unit,
                'unit_price_cents' => $effectivePrice,
                'quantity' => $qty,
                'vat_rate' => (string) ($service->vat_rate ?? '23'),
            ];
        }

        return $items;
    }

    /**
     * Tworzy Invoice draft + items + recompute totals. Wszystkie
     * snapshot'y (seller/buyer info) z aktualnych rekordów stable
     * tenant + client. Numer NIE generowany (draft) — wygeneruje się
     * gdy operator stajni kliknie "Wystaw" w InvoiceResource.
     *
     * @param  list<array{name: string, unit: string, unit_price_cents: int, quantity: float, vat_rate: string}>  $items
     */
    private function createInvoice(
        Client $client,
        Horse $horse,
        HorseBoardingAssignment $assignment,
        Carbon $period,
        array $items,
        Tenant $stableTenant,
    ): Invoice {
        $invoice = Invoice::create([
            'id' => (string) Str::ulid(),
            'kind' => InvoiceKind::Fv,
            'status' => InvoiceStatus::Draft,
            'client_id' => $client->id,
            'seller_name' => $stableTenant->legal_name ?: $stableTenant->name,
            'seller_nip' => $stableTenant->tax_id ?: null,
            'buyer_name' => $client->name,
            'buyer_nip' => $client->tax_id ?: null,
            'currency' => 'PLN',
            'subtotal_cents' => 0,
            'vat_cents' => 0,
            'total_cents' => 0,
            'sale_date' => $period->copy()->endOfMonth()->toDateString(),
            'metadata' => [
                'billing_period' => $this->periodIso,
                'source' => 'auto_boarding',
                'boarding_assignment_id' => $assignment->id,
                'central_horse_id' => $assignment->central_horse_id,
                'horse_name' => $horse->name,
            ],
        ]);

        $position = 1;
        foreach ($items as $item) {
            InvoiceItem::create([
                'id' => (string) Str::ulid(),
                'invoice_id' => $invoice->id,
                'horse_id' => $assignment->central_horse_id,
                'position' => $position++,
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'],
                'vat_rate' => $item['vat_rate'],
                'unit_price_cents' => $item['unit_price_cents'],
                'net_cents' => 0,
                'vat_cents' => 0,
                'total_cents' => 0,
            ])->recomputeAmounts()->save();
        }

        // Recompute totals z fresh items (musimy reload bo InvoiceItem::create
        // nie pushuje do relation collection automatycznie).
        $invoice->load('items');
        $invoice->recomputeTotals()->save();

        return $invoice;
    }
}
