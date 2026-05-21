<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\Invoicing\Owner\OwnerInvoiceFeedService;
use App\Domain\Invoicing\Owner\Snapshots\InvoiceDetailSnapshot;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

/**
 * Owner panel: szczegóły pojedynczej faktury — composite identifier
 * (stableTenantId, invoiceId) w URL'u bo invoice ID nie jest globalnie
 * unikalny.
 *
 * Mount flow:
 *   1. Auth check (Filament authMiddleware już to robi)
 *   2. OwnerInvoiceFeedService::findInvoice — wewnętrzny gate (Client
 *      .central_user_id matching) + skip draftów
 *   3. 404 jeśli null (brak access lub draft lub missing)
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 3 PR 3.4".
 */
class InvoiceShow extends Page
{
    protected static ?string $slug = 'invoices/{stableTenantId}/{invoiceId}';

    protected static string $view = 'filament.owner.pages.invoice-show';

    protected static bool $shouldRegisterNavigation = false;

    public string $stableTenantId = '';

    public string $invoiceId = '';

    public ?InvoiceDetailSnapshot $invoice = null;

    public function getTitle(): string|Htmlable
    {
        if ($this->invoice?->number !== null) {
            return __('owner/invoices.show.title', ['number' => $this->invoice->number]);
        }

        return __('owner/invoices.show.title_draft');
    }

    public function getBreadcrumbs(): array
    {
        return [
            InvoiceList::getUrl() => __('owner/invoices.navigation'),
            __('owner/invoices.show.back_to_list') => '',
        ];
    }

    public function mount(string $stableTenantId, string $invoiceId): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $this->stableTenantId = $stableTenantId;
        $this->invoiceId = $invoiceId;

        $this->invoice = app(OwnerInvoiceFeedService::class)
            ->findInvoice($user, $stableTenantId, $invoiceId);

        abort_if($this->invoice === null, 404);
    }

    public function formatCents(int $cents, ?string $currency = null): string
    {
        $cur = $currency ?? $this->invoice?->currency ?? 'PLN';

        return number_format($cents / 100, 2, ',', ' ').' '.$cur;
    }
}
