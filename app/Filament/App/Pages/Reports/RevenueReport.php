<?php

declare(strict_types=1);

namespace App\Filament\App\Pages\Reports;

use App\Enums\InvoiceStatus;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Models\Tenant\Invoice;
use App\Services\Reports\MonthRange;
use App\Services\Tenancy\TenantRoleGate;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;

/**
 * Monthly revenue breakdown — sums net invoice items in the picked
 * month, bucketed by simple name-substring heuristic into pensjonat /
 * lekcje / karnety / inne. Buckets are not authoritative — owner can
 * always inspect the raw invoices via the link to /app/invoices.
 */
class RevenueReport extends Page
{
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::FINANCE_STAFF;
    }

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 91;

    protected static string $view = 'filament.app.pages.reports.revenue';

    #[Url]
    public ?string $month = null;

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.reports');
    }

    public static function getNavigationLabel(): string
    {
        return __('pages.reports.revenue.navigation');
    }

    public function getTitle(): string|Htmlable
    {
        return __('pages.reports.revenue.title');
    }

    public function range(): MonthRange
    {
        return MonthRange::from($this->month);
    }

    /**
     * @return array{
     *     range:MonthRange,
     *     total_cents:int,
     *     invoice_count:int,
     *     buckets: array<string, int>,
     *     top_items: array<int, array{name:string, total_cents:int}>,
     * }
     */
    public function snapshot(): array
    {
        $range = $this->range();

        $invoices = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::Paid->value])
            ->whereBetween('issued_at', [$range->start->toDateString(), $range->end->toDateString()])
            ->with('items')
            ->get();

        $buckets = [
            'boarding' => 0,
            'lessons' => 0,
            'passes' => 0,
            'other' => 0,
        ];
        $topItems = [];

        foreach ($invoices as $invoice) {
            foreach ($invoice->items as $item) {
                $buckets[$this->bucketFor((string) $item->name)] += (int) $item->net_cents;
                $topItems[] = ['name' => (string) $item->name, 'total_cents' => (int) $item->net_cents];
            }
        }

        // Aggregate by name across all invoices, take top 10.
        $aggregated = collect($topItems)
            ->groupBy('name')
            ->map(fn ($rows, $name) => [
                'name' => (string) $name,
                'total_cents' => (int) $rows->sum('total_cents'),
            ])
            ->sortByDesc('total_cents')
            ->take(10)
            ->values()
            ->all();

        return [
            'range' => $range,
            'total_cents' => array_sum($buckets),
            'invoice_count' => $invoices->count(),
            'buckets' => $buckets,
            'top_items' => $aggregated,
        ];
    }

    private function bucketFor(string $name): string
    {
        $name = mb_strtolower($name);

        return match (true) {
            str_contains($name, 'pensj') || str_contains($name, 'boarding') => 'boarding',
            str_contains($name, 'lekcj') || str_contains($name, 'lesson') || str_contains($name, 'training') => 'lessons',
            str_contains($name, 'karnet') || str_contains($name, 'pass') => 'passes',
            default => 'other',
        };
    }

    public function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ').' zł';
    }
}
