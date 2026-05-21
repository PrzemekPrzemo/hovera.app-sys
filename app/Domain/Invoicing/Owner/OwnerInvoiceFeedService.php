<?php

declare(strict_types=1);

namespace App\Domain\Invoicing\Owner;

use App\Domain\Invoicing\Owner\Snapshots\InvoiceDetailSnapshot;
use App\Domain\Invoicing\Owner\Snapshots\InvoiceItemSnapshot;
use App\Domain\Invoicing\Owner\Snapshots\InvoiceSummarySnapshot;
use App\Enums\InvoiceStatus;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Tenant\Client;
use App\Models\Tenant\Invoice;
use App\Tenancy\TenantManager;
use Illuminate\Support\Collection;

/**
 * Cross-tenant aggregator faktur dla owner panel'u. Owner ma assignment'y
 * w wielu stable tenant'ach (active + ended history); ten serwis odwiedza
 * każdego tenant'a, czyta jego invoices scoped przez Client.central_user_id,
 * mapuje do DTO.
 *
 * Każdy tenant odwiedzany przez `TenantManager::execute()` z restore
 * w finally. DTO są self-contained (post-execute connection nie zna
 * stable schema).
 *
 * Performance:
 *   - Globalny list: N+1 dla tenant'ów ownera (rzadko >5 stable'ów w karierze)
 *   - Per-koń list: 1 tenant (active assignment dla central_horse_id)
 *   - Single invoice: 1 tenant (gate ustala który stable)
 *
 * Pomijamy `draft` invoices — owner widzi dopiero po stable.issue, status
 * != draft. Bez tego owner widziałby "robocze" które operator stajni
 * jeszcze edytuje (np. po auto-billing job).
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 3 PR 3.3".
 */
class OwnerInvoiceFeedService
{
    public function __construct(
        private readonly TenantManager $tenants,
    ) {}

    /**
     * Wszystkie issued+ faktury ownera, posortowane DESC po issued_at.
     * Łączy wyniki z N stable tenant'ów (active + ended boarding'ów).
     *
     * @return Collection<int, InvoiceSummarySnapshot>
     */
    public function forOwner(User $owner): Collection
    {
        $tenantIds = $this->ownerStableTenantIds($owner);
        $all = collect();

        foreach ($tenantIds as $tenantId) {
            $stableTenant = Tenant::query()->find($tenantId);
            if ($stableTenant === null) {
                continue;
            }

            $items = $this->tenants->execute($stableTenant, function () use ($stableTenant, $owner): array {
                return $this->listIssuedForOwnerInStable($stableTenant, $owner, null);
            });
            $all = $all->concat($items);
        }

        return $all->sortByDesc(fn (InvoiceSummarySnapshot $i) => $i->issuedAt?->getTimestamp() ?? 0)->values();
    }

    /**
     * Faktury filtrowane do konkretnego konia — pokrywa invoice_items
     * gdzie horse_id = centralHorseId. Stable resolveowany z active
     * assignment'u (jeśli ended, owner widzi historyczne via forOwner).
     *
     * @return Collection<int, InvoiceSummarySnapshot>
     */
    public function forHorse(User $owner, string $centralHorseId): Collection
    {
        // Owner ma maks 1 active assignment dla danego konia, ale może mieć
        // też ended assignment'y w innych stable'ach — zwracamy z każdego.
        $assignments = HorseBoardingAssignment::query()
            ->where('owner_user_id', $owner->id)
            ->where('central_horse_id', $centralHorseId)
            ->get();

        $all = collect();
        foreach ($assignments as $assignment) {
            $stableTenant = Tenant::query()->find($assignment->stable_tenant_id);
            if ($stableTenant === null) {
                continue;
            }

            $items = $this->tenants->execute($stableTenant, function () use ($stableTenant, $owner, $centralHorseId): array {
                return $this->listIssuedForOwnerInStable($stableTenant, $owner, $centralHorseId);
            });
            $all = $all->concat($items);
        }

        return $all->sortByDesc(fn (InvoiceSummarySnapshot $i) => $i->issuedAt?->getTimestamp() ?? 0)->values();
    }

    /**
     * Szczegółowy snapshot pojedynczej faktury. Zwraca null jeśli:
     *   - Stable tenant nie istnieje
     *   - Invoice nie ma w stable DB
     *   - Owner nie ma matching client (Client.central_user_id) w stable
     *   - Invoice status = draft (owner widzi tylko issued+)
     */
    public function findInvoice(User $owner, string $stableTenantId, string $invoiceId): ?InvoiceDetailSnapshot
    {
        $stableTenant = Tenant::query()->find($stableTenantId);
        if ($stableTenant === null) {
            return null;
        }

        return $this->tenants->execute($stableTenant, function () use ($stableTenant, $owner, $invoiceId): ?InvoiceDetailSnapshot {
            $client = Client::query()->where('central_user_id', $owner->id)->first();
            if ($client === null) {
                return null;
            }

            $invoice = Invoice::query()
                ->with('items')
                ->where('id', $invoiceId)
                ->where('client_id', $client->id)
                ->first();

            if ($invoice === null) {
                return null;
            }

            // Owner nie powinien widzieć draftów (in-progress edits stajni).
            if ($invoice->status === InvoiceStatus::Draft) {
                return null;
            }

            return $this->mapToDetail($invoice, $stableTenant);
        });
    }

    /**
     * Lista stable tenant ID'ów które należy odpytać dla tego ownera —
     * unique z aktywnych i zakończonych assignment'ów. Pending pomijamy
     * (nie generują jeszcze faktur).
     *
     * @return list<string>
     */
    private function ownerStableTenantIds(User $owner): array
    {
        return HorseBoardingAssignment::query()
            ->where('owner_user_id', $owner->id)
            ->whereIn('status', [
                HorseBoardingAssignment::STATUS_ACTIVE,
                HorseBoardingAssignment::STATUS_ENDED,
            ])
            ->distinct()
            ->pluck('stable_tenant_id')
            ->all();
    }

    /**
     * Yearly totals — `[year => totalCents]` agregowane przez wszystkie
     * stables ownera. Używane przez C.7 historia rozliczeń (banner +
     * dropdown wyboru roku do filtrowania).
     *
     * @return array<int, int>  klucz = rok (np. 2026), value = suma total_cents
     */
    public function yearlyTotalsForOwner(User $owner): array
    {
        $tenantIds = $this->ownerStableTenantIds($owner);
        $totals = [];

        foreach ($tenantIds as $tenantId) {
            $stable = Tenant::query()->find($tenantId);
            if ($stable === null) {
                continue;
            }
            // PHP-side aggregation żeby uniknąć driver-specific funkcji
            // SQL (strftime na sqlite, YEAR() na MySQL). Liczba faktur
            // per owner jest small (~100/rok) więc OK żeby się przejść.
            $perStable = $this->tenants->execute($stable, function () use ($owner): array {
                $client = Client::query()->where('central_user_id', $owner->id)->first();
                if ($client === null) {
                    return [];
                }

                $rows = Invoice::query()
                    ->where('client_id', $client->id)
                    ->where('status', '!=', InvoiceStatus::Draft->value)
                    ->whereNotNull('issued_at')
                    ->get(['issued_at', 'total_cents']);

                $totals = [];
                foreach ($rows as $row) {
                    $year = (int) $row->issued_at->format('Y');
                    $totals[$year] = ($totals[$year] ?? 0) + (int) $row->total_cents;
                }

                return $totals;
            });

            foreach ($perStable as $year => $total) {
                $year = (int) $year;
                $totals[$year] = ($totals[$year] ?? 0) + (int) $total;
            }
        }

        krsort($totals);

        return $totals;
    }

    /**
     * Lista faktur ownera filtrowana do konkretnego roku — używana
     * przez C.7 (year filter chip + CSV export).
     *
     * @return Collection<int, InvoiceSummarySnapshot>
     */
    public function forOwnerYear(User $owner, int $year): Collection
    {
        $tenantIds = $this->ownerStableTenantIds($owner);
        $all = collect();

        foreach ($tenantIds as $tenantId) {
            $stable = Tenant::query()->find($tenantId);
            if ($stable === null) {
                continue;
            }

            $items = $this->tenants->execute($stable, function () use ($stable, $owner, $year): array {
                return $this->listIssuedForOwnerInStable($stable, $owner, null, $year);
            });
            $all = $all->concat($items);
        }

        return $all->sortByDesc(fn (InvoiceSummarySnapshot $i) => $i->issuedAt?->getTimestamp() ?? 0)->values();
    }

    /**
     * Wewnątrz execute() — czyta issued invoices ze stable scoped per
     * Client.central_user_id, opcjonalnie filtrowane do central_horse_id
     * i/lub do konkretnego roku (issued_at YEAR matching).
     *
     * @return list<InvoiceSummarySnapshot>
     */
    private function listIssuedForOwnerInStable(Tenant $stable, User $owner, ?string $centralHorseId, ?int $year = null): array
    {
        $client = Client::query()->where('central_user_id', $owner->id)->first();
        if ($client === null) {
            return [];
        }

        $query = Invoice::query()
            ->where('client_id', $client->id)
            ->where('status', '!=', InvoiceStatus::Draft->value);

        if ($centralHorseId !== null) {
            // Filtr per koń — invoice ma item z horse_id = centralHorseId.
            // EXISTS subquery na invoice_items.
            $query->whereExists(function ($sub) use ($centralHorseId) {
                $sub->selectRaw(1)
                    ->from('invoice_items')
                    ->whereColumn('invoice_items.invoice_id', 'invoices.id')
                    ->where('invoice_items.horse_id', $centralHorseId);
            });
        }

        if ($year !== null) {
            $query->whereBetween('issued_at', [
                sprintf('%d-01-01 00:00:00', $year),
                sprintf('%d-12-31 23:59:59', $year),
            ]);
        }

        $invoices = $query->orderByDesc('issued_at')->get();

        $out = [];
        foreach ($invoices as $invoice) {
            $out[] = $this->mapToSummary($invoice, $stable);
        }

        return $out;
    }

    private function mapToSummary(Invoice $invoice, Tenant $stable): InvoiceSummarySnapshot
    {
        $metadata = is_array($invoice->metadata) ? $invoice->metadata : [];

        return new InvoiceSummarySnapshot(
            id: (string) $invoice->id,
            stableTenantId: (string) $stable->id,
            stableTenantName: (string) $stable->name,
            number: $invoice->number !== null ? (string) $invoice->number : null,
            kind: $invoice->kind->value,
            status: $invoice->status->value,
            issuedAt: $invoice->issued_at,
            dueAt: $invoice->due_at,
            paidAt: $invoice->paid_at,
            currency: (string) $invoice->currency,
            totalCents: (int) $invoice->total_cents,
            billingPeriod: isset($metadata['billing_period']) ? (string) $metadata['billing_period'] : null,
            centralHorseId: isset($metadata['central_horse_id']) ? (string) $metadata['central_horse_id'] : null,
            horseName: isset($metadata['horse_name']) ? (string) $metadata['horse_name'] : null,
        );
    }

    private function mapToDetail(Invoice $invoice, Tenant $stable): InvoiceDetailSnapshot
    {
        $metadata = is_array($invoice->metadata) ? $invoice->metadata : [];

        $items = [];
        foreach ($invoice->items as $item) {
            $items[] = new InvoiceItemSnapshot(
                id: (string) $item->id,
                horseId: $item->horse_id !== null ? (string) $item->horse_id : null,
                position: (int) $item->position,
                name: (string) $item->name,
                description: $item->description !== null ? (string) $item->description : null,
                quantity: (float) $item->quantity,
                unit: (string) $item->unit,
                vatRate: (string) $item->vat_rate,
                unitPriceCents: (int) $item->unit_price_cents,
                netCents: (int) $item->net_cents,
                vatCents: (int) $item->vat_cents,
                totalCents: (int) $item->total_cents,
            );
        }

        return new InvoiceDetailSnapshot(
            id: (string) $invoice->id,
            stableTenantId: (string) $stable->id,
            stableTenantName: (string) $stable->name,
            number: $invoice->number !== null ? (string) $invoice->number : null,
            kind: $invoice->kind->value,
            status: $invoice->status->value,
            issuedAt: $invoice->issued_at,
            saleDate: $invoice->sale_date,
            dueAt: $invoice->due_at,
            paidAt: $invoice->paid_at,
            sellerName: (string) $invoice->seller_name,
            sellerNip: $invoice->seller_nip !== null ? (string) $invoice->seller_nip : null,
            sellerAddress: $invoice->seller_address !== null ? (string) $invoice->seller_address : null,
            sellerCity: $invoice->seller_city !== null ? (string) $invoice->seller_city : null,
            sellerPostalCode: $invoice->seller_postal_code !== null ? (string) $invoice->seller_postal_code : null,
            buyerName: (string) $invoice->buyer_name,
            buyerNip: $invoice->buyer_nip !== null ? (string) $invoice->buyer_nip : null,
            buyerAddress: $invoice->buyer_address !== null ? (string) $invoice->buyer_address : null,
            buyerCity: $invoice->buyer_city !== null ? (string) $invoice->buyer_city : null,
            buyerPostalCode: $invoice->buyer_postal_code !== null ? (string) $invoice->buyer_postal_code : null,
            currency: (string) $invoice->currency,
            subtotalCents: (int) $invoice->subtotal_cents,
            vatCents: (int) $invoice->vat_cents,
            totalCents: (int) $invoice->total_cents,
            items: $items,
            notes: $invoice->notes !== null ? (string) $invoice->notes : null,
            billingPeriod: isset($metadata['billing_period']) ? (string) $metadata['billing_period'] : null,
            centralHorseId: isset($metadata['central_horse_id']) ? (string) $metadata['central_horse_id'] : null,
            horseName: isset($metadata['horse_name']) ? (string) $metadata['horse_name'] : null,
        );
    }
}
