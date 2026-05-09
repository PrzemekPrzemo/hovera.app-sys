<?php

declare(strict_types=1);

namespace App\Filament\App\Pages\Reports;

use App\Enums\InvoiceStatus;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Models\Tenant\Invoice;
use App\Services\Tenancy\TenantRoleGate;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;

/**
 * Receivables aging — unpaid (status=Issued) invoices grouped by how
 * many days they're overdue. Color gradient (green/amber/red/dark)
 * makes the worst debtors jump out at a glance.
 */
class ReceivablesAgingReport extends Page
{
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::FINANCE_STAFF;
    }

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = 92;

    protected static string $view = 'filament.app.pages.reports.aging';

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.reports');
    }

    public static function getNavigationLabel(): string
    {
        return __('pages.reports.aging.navigation');
    }

    public function getTitle(): string|Htmlable
    {
        return __('pages.reports.aging.title');
    }

    /**
     * @return array{
     *     today: Carbon,
     *     totals: array{0_30:int, 31_60:int, 61_90:int, 90_plus:int, total:int},
     *     rows: \Illuminate\Support\Collection,
     * }
     */
    public function snapshot(): array
    {
        $today = now()->startOfDay();

        $invoices = Invoice::query()
            ->where('status', InvoiceStatus::Issued->value)
            ->whereNotNull('due_at')
            ->where('due_at', '<', $today->toDateString())
            ->with('client:id,name,email')
            ->orderBy('due_at')
            ->get();

        $totals = ['0_30' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0, 'total' => 0];

        $rows = $invoices->map(function (Invoice $i) use ($today, &$totals) {
            $daysOverdue = (int) Carbon::parse($i->due_at)->diffInDays($today);
            $bucket = match (true) {
                $daysOverdue <= 30 => '0_30',
                $daysOverdue <= 60 => '31_60',
                $daysOverdue <= 90 => '61_90',
                default => '90_plus',
            };
            $totals[$bucket] += (int) $i->total_cents;
            $totals['total'] += (int) $i->total_cents;

            return [
                'invoice' => $i,
                'days_overdue' => $daysOverdue,
                'bucket' => $bucket,
            ];
        });

        return [
            'today' => $today,
            'totals' => $totals,
            'rows' => $rows,
        ];
    }

    public function colorForBucket(string $bucket): string
    {
        return match ($bucket) {
            '0_30' => 'warning',
            '31_60' => 'orange',
            '61_90' => 'danger',
            default => 'rose',
        };
    }

    public function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ').' zł';
    }
}
