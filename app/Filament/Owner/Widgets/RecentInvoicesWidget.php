<?php

declare(strict_types=1);

namespace App\Filament\Owner\Widgets;

use App\Domain\Invoicing\Owner\OwnerInvoiceFeedService;
use App\Models\Central\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

/**
 * Dashboard widget — "Ostatnie faktury" (max 5) ze stajni/przewoźników
 * powiązanych z owner'em. Bezpośredni link do `/owner/invoices/{stableId}/
 * {invoiceId}` (InvoiceShow page) zamiast szukania w pełnej liście.
 *
 * Komplementarny do `LastOwnerActivityWidget` (generic notification feed) —
 * tu specifically faktury z linkiem do akcji, tam ogólny strumień
 * (notyfikacje od stajni o koniu, vet visits itp.).
 *
 * Sort = -4 — pod kpi statystykami (-5 default), ale powyżej generic
 * activity widget. Dziel uwagę dashboard'u: status onboardingu (top) →
 * KPI → faktury → ogólny aktywność.
 */
class RecentInvoicesWidget extends Widget
{
    protected static ?int $sort = -4;

    protected static string $view = 'filament.owner.widgets.recent-invoices';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return false;
        }

        return app(OwnerInvoiceFeedService::class)
            ->forOwner($user)
            ->isNotEmpty();
    }

    /**
     * @return list<array{id:string, stable_tenant_id:string, stable_name:string, number:?string, total:string, currency:string, issued_at:?string, paid:bool, url:string}>
     */
    public function getRecent(): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return [];
        }

        $snapshots = app(OwnerInvoiceFeedService::class)
            ->forOwner($user)
            ->take(5);

        $out = [];
        foreach ($snapshots as $s) {
            $out[] = [
                'id' => $s->id,
                'stable_tenant_id' => $s->stableTenantId,
                'stable_name' => $s->stableTenantName,
                'number' => $s->number,
                'total' => number_format($s->totalCents / 100, 2, ',', ' '),
                'currency' => $s->currency,
                'issued_at' => $s->issuedAt?->format('Y-m-d'),
                'paid' => $s->paidAt !== null,
                'url' => "/owner/invoices/{$s->stableTenantId}/{$s->id}",
            ];
        }

        return $out;
    }
}
