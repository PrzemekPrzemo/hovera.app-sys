<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\Invoicing\Owner\OwnerInvoiceFeedService;
use App\Domain\Invoicing\Owner\Snapshots\InvoiceSummarySnapshot;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Owner panel: globalna lista wszystkich faktur (across all stable
 * tenant'ów). Lista snapshot'ów z OwnerInvoiceFeedService, sortowana
 * DESC po issued_at. Pomijamy draft'y.
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 3 PR 3.4".
 */
class InvoiceList extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 30;

    protected static string $view = 'filament.owner.pages.invoice-list';

    protected static ?string $slug = 'invoices';

    /** @var Collection<int, InvoiceSummarySnapshot>|null */
    public ?Collection $invoices = null;

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.owner_finance');
    }

    public static function getNavigationLabel(): string
    {
        return __('owner/invoices.navigation');
    }

    public function getTitle(): string|Htmlable
    {
        return __('owner/invoices.list.title');
    }

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $this->invoices = app(OwnerInvoiceFeedService::class)->forOwner($user);
    }

    /**
     * Helper: formatuje cents do "1 234,56 PLN".
     */
    public function formatCents(int $cents, string $currency = 'PLN'): string
    {
        return number_format($cents / 100, 2, ',', ' ').' '.$currency;
    }

    /**
     * Helper: zwraca route URL do InvoiceShow z composite ID.
     */
    public function showUrl(InvoiceSummarySnapshot $invoice): string
    {
        return InvoiceShow::getUrl([
            'stableTenantId' => $invoice->stableTenantId,
            'invoiceId' => $invoice->id,
        ]);
    }
}
