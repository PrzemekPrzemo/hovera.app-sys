<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Payments\InitiatePayment;
use App\Enums\InvoiceStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Invoice;
use App\Tenancy\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Publiczny widok faktury z signed URL. Klient dostaje link mailem,
 * widzi fakturę + przycisk "Zapłać teraz" (jeśli stajnia ma
 * skonfigurowanego payment providera).
 *
 * Status update na fakturze (paid_at) ustawia PaymentObserver gdy
 * provider webhook potwierdzi succeeded — nie mark'ujemy tu.
 */
class PublicInvoiceController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenants,
        private readonly InitiatePayment $initiate,
    ) {}

    public function show(Request $request, string $slug, string $invoiceId): View|RedirectResponse
    {
        $tenant = $this->resolveAndActivate($slug);

        if (! $request->hasValidSignature()) {
            return view('public.invoice.invalid', [
                'tenant' => $tenant,
                'reason' => 'expired',
                'primary_color' => data_get($tenant->branding, 'primary_color', '#10b981'),
            ]);
        }

        $invoice = Invoice::query()->with(['client', 'items'])->find($invoiceId);
        if (! $invoice || ! $invoice->status->isPosted()) {
            return view('public.invoice.invalid', [
                'tenant' => $tenant,
                'reason' => 'not_found',
                'primary_color' => data_get($tenant->branding, 'primary_color', '#10b981'),
            ]);
        }

        $canPayOnline = $invoice->status === InvoiceStatus::Issued
            && $this->hasPaymentProvider($tenant);

        return view('public.invoice.show', [
            'tenant' => $tenant,
            'invoice' => $invoice,
            'can_pay_online' => $canPayOnline,
            'pay_url' => url()->temporarySignedRoute(
                'public.invoice.pay',
                $invoice->due_at ? $invoice->due_at->copy()->addDays(14) : now()->addDays(90),
                ['slug' => $slug, 'invoice' => $invoice->id],
            ),
            'primary_color' => data_get($tenant->branding, 'primary_color', '#10b981'),
        ]);
    }

    public function pay(Request $request, string $slug, string $invoiceId): RedirectResponse|View
    {
        $tenant = $this->resolveAndActivate($slug);

        if (! $request->hasValidSignature()) {
            return view('public.invoice.invalid', [
                'tenant' => $tenant,
                'reason' => 'expired',
                'primary_color' => data_get($tenant->branding, 'primary_color', '#10b981'),
            ]);
        }

        $invoice = Invoice::query()->with('client')->find($invoiceId);
        if (! $invoice || $invoice->status !== InvoiceStatus::Issued || ! $invoice->client) {
            return view('public.invoice.invalid', [
                'tenant' => $tenant,
                'reason' => 'not_found',
                'primary_color' => data_get($tenant->branding, 'primary_color', '#10b981'),
            ]);
        }

        try {
            $payment = $this->initiate->execute(
                $tenant,
                $invoice->client,
                amountCents: (int) $invoice->total_cents,
                currency: $invoice->currency ?? 'PLN',
                context: [
                    'metadata' => ['invoice_number' => (string) $invoice->number],
                ],
            );

            // Link payment do faktury — observer załatwi mark Paid po webhooku
            $payment->forceFill(['invoice_id' => $invoice->id])->save();

            return redirect()->away((string) $payment->checkout_url);
        } catch (\Throwable $e) {
            report($e);

            return view('public.invoice.invalid', [
                'tenant' => $tenant,
                'reason' => 'payment_error',
                'message' => $e->getMessage(),
                'primary_color' => data_get($tenant->branding, 'primary_color', '#10b981'),
            ]);
        }
    }

    private function hasPaymentProvider(Tenant $tenant): bool
    {
        $provider = (string) (data_get($tenant->settings, 'payments.default_provider') ?? 'none');

        return $provider !== 'none';
    }

    private function resolveAndActivate(string $slug): Tenant
    {
        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $slug)) {
            abort(404);
        }

        $tenant = Cache::remember(
            "public_booking_tenant:{$slug}",
            now()->addMinute(),
            fn () => Tenant::query()
                ->where('slug', $slug)
                ->whereIn('status', ['trialing', 'active', 'past_due'])
                ->first(),
        );

        if (! $tenant) {
            abort(404);
        }

        if ($this->tenants->current()?->id !== $tenant->id) {
            $this->tenants->setCurrent($tenant);
        }

        return $tenant;
    }
}
