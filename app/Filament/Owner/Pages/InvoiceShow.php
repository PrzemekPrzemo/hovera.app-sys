<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\Invoicing\Owner\OwnerInvoiceFeedService;
use App\Domain\Invoicing\Owner\OwnerInvoicePaymentService;
use App\Domain\Invoicing\Owner\Snapshots\InvoiceDetailSnapshot;
use App\Models\Central\Tenant;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

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

    /**
     * Czy "Zapłać online" button powinien być widoczny. UI sprawdza:
     *   - faktura w statusie issued/overdue (NIE paid/draft/void/cancelled)
     *   - stable ma skonfigurowany payment provider (`payments.default_provider`)
     *
     * Filament Livewire renderuje state przy każdym request, więc OK
     * żeby method robił DB lookup (1 row, cached przy renderze).
     */
    public function canPay(): bool
    {
        if ($this->invoice === null) {
            return false;
        }
        if (! in_array($this->invoice->status, ['issued', 'overdue'], true)) {
            return false;
        }

        $stable = Tenant::query()->find($this->stableTenantId);
        if ($stable === null) {
            return false;
        }

        return app(OwnerInvoicePaymentService::class)->stableSupportsPayments($stable);
    }

    /**
     * Inicjuje payment session via OwnerInvoicePaymentService. Po sukcesie
     * redirectuje user'a na hosted checkout URL providera (302). W razie
     * błędu Notification danger + zostajemy na stronie.
     *
     * Wywoływane przez Livewire wire:click w blade'cie.
     */
    public function pay(): ?RedirectResponse
    {
        $user = Auth::user();
        if ($user === null) {
            return null;
        }

        try {
            $url = app(OwnerInvoicePaymentService::class)
                ->initiate($user, $this->stableTenantId, $this->invoiceId);

            return redirect()->away($url);
        } catch (AuthorizationException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        } catch (Throwable $e) {
            report($e);
            Log::warning('Owner pay button failed', [
                'user_id' => $user->id,
                'stable_tenant_id' => $this->stableTenantId,
                'invoice_id' => $this->invoiceId,
                'error' => $e->getMessage(),
            ]);
            Notification::make()->danger()->title($e->getMessage())->send();
        }

        return null;
    }
}
