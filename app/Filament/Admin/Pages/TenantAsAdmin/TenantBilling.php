<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages\TenantAsAdmin;

use App\Models\Central\Tenant;
use App\Services\Billing\StripeBillingService;
use App\Services\MasterAuditLogger;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

/**
 * Master-admin billing history for a single tenant. Pulls invoices
 * straight from Stripe (we don't keep a `central.invoices` mirror yet —
 * Trial 2.0 will add that). The page also exposes a "Refund" action
 * per row that calls StripeBillingService::refundInvoice() with the
 * operator's confirmation + reason.
 */
class TenantBilling extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.admin.pages.tenant-as-admin.billing';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'tenants/{tenantId}/billing';

    public string $tenantId = '';

    /** @var list<array<string,mixed>> */
    public array $invoices = [];

    public ?string $loadError = null;

    public ?string $refundInvoiceId = null;

    /** @var array<string,mixed> */
    public array $refundData = [];

    public function getTitle(): string|Htmlable
    {
        return __('admin/back-office.billing.title', ['name' => $this->tenant()->name]);
    }

    public function getBreadcrumbs(): array
    {
        return [
            url('/admin/tenants') => __('navigation.tenants'),
            url('/admin/tenants/'.$this->tenant()->id.'/edit') => $this->tenant()->name,
            __('admin/back-office.billing.breadcrumb') => '',
        ];
    }

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->is_master_admin;
    }

    public function mount(string $tenantId): void
    {
        abort_unless(self::canAccess(), 403);
        $this->tenantId = $tenantId;
        $this->loadInvoices();
    }

    public function loadInvoices(): void
    {
        $tenant = $this->tenant();
        $this->loadError = null;

        if ($tenant->stripe_customer_id === null) {
            $this->invoices = [];

            return;
        }

        try {
            $this->invoices = app(StripeBillingService::class)->listInvoices($tenant, 50);
        } catch (\Throwable $e) {
            $this->invoices = [];
            $this->loadError = $e->getMessage();
        }
    }

    public function startRefund(string $invoiceId): void
    {
        abort_unless(self::canAccess(), 403);
        $this->refundInvoiceId = $invoiceId;
        $this->refundData = ['reason' => '', 'amount_cents' => null, 'full' => true];
    }

    public function cancelRefund(): void
    {
        $this->refundInvoiceId = null;
        $this->refundData = [];
    }

    public function confirmRefund(): void
    {
        abort_unless(self::canAccess(), 403);
        $invoiceId = $this->refundInvoiceId;
        if ($invoiceId === null) {
            return;
        }

        $reason = trim((string) ($this->refundData['reason'] ?? ''));
        if (strlen($reason) < 5) {
            Notification::make()->danger()
                ->title(__('admin/back-office.billing.refund.reason_required'))
                ->send();

            return;
        }

        $full = (bool) ($this->refundData['full'] ?? true);
        $amount = $full ? null : (int) ($this->refundData['amount_cents'] ?? 0);
        if (! $full && ($amount === null || $amount <= 0)) {
            Notification::make()->danger()
                ->title(__('admin/back-office.billing.refund.amount_required'))
                ->send();

            return;
        }

        try {
            $result = app(StripeBillingService::class)
                ->refundInvoice($invoiceId, $amount, $reason);
        } catch (\Throwable $e) {
            Notification::make()->danger()
                ->title(__('admin/back-office.billing.refund.failed'))
                ->body($e->getMessage())
                ->send();

            return;
        }

        app(MasterAuditLogger::class)->record(
            'tenant.billing.refund',
            'StripeInvoice',
            $invoiceId,
            $this->tenantId,
            [
                'refund_id' => $result['id'],
                'amount_refunded' => $result['amount'],
                'status' => $result['status'],
                'reason' => $reason,
            ],
        );

        Notification::make()->success()
            ->title(__('admin/back-office.billing.refund.success'))
            ->body(__('admin/back-office.billing.refund.success_body', ['id' => $result['id']]))
            ->send();

        $this->cancelRefund();
        $this->loadInvoices();
    }

    /**
     * Schema for the refund modal — kept simple on purpose, the operator
     * picks full vs partial and types a reason.
     */
    public function refundForm(): array
    {
        return [
            Forms\Components\Toggle::make('full')
                ->label(__('admin/back-office.billing.refund.full_label'))
                ->default(true)
                ->reactive(),
            Forms\Components\TextInput::make('amount_cents')
                ->label(__('admin/back-office.billing.refund.amount_label'))
                ->numeric()
                ->minValue(1)
                ->visible(fn (Forms\Get $get) => ! $get('full'))
                ->helperText(__('admin/back-office.billing.refund.amount_helper')),
            Forms\Components\Textarea::make('reason')
                ->label(__('admin/back-office.billing.refund.reason_label'))
                ->required()
                ->minLength(5)
                ->maxLength(500),
        ];
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->withTrashed()->findOrFail($this->tenantId);
    }
}
