<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Central\Invoice;
use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Services\Billing\PayUService;
use App\Services\Billing\StripeBillingService;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Tenant-facing billing flow:
 *   GET  /app/billing            → plan picker + manage subscription
 *   POST /app/billing/checkout   → Stripe Checkout redirect
 *   GET  /app/billing/return     → success page (after Checkout)
 *   POST /app/billing/portal     → Customer Portal redirect
 *
 * Access is gated by membership role at action level (not at route
 * level — the panel's auth middleware already enforces login).
 */
class BillingController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenants,
    ) {}

    // Ani StripeBillingService, ani PayUService NIE są wstrzykiwane w
    // konstruktorze celowo: ich singletony rzucają, gdy brak konfiguracji
    // (puste STRIPE_SECRET / PAYU_POS_ID), co wywaliłoby CAŁĄ stronę
    // /app/billing — nawet `show()`, który płatności w ogóle nie dotyka.
    // Rozwiązujemy je leniwie tylko w akcjach, które ich używają
    // (checkout/portal/payuCheckout), gdzie istniejący try/catch zamienia
    // brak konfiguracji w przyjazny błąd zamiast 500.

    public function show(Request $request): ViewContract|RedirectResponse|Renderable
    {
        $tenant = $this->resolveTenant();
        if ($tenant === null) {
            return redirect()->route('tenant.select');
        }
        $this->authorizeAdmin($tenant);

        $plans = Plan::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->get();

        // `?plan=pro` z banera → highlight tej karty + scroll-into-view.
        // Whitelistujemy do listy aktywnych public planów żeby nie podbić
        // jakiegoś hidden Enterprise.
        $suggested = (string) $request->query('plan', '');
        $suggestedPlan = $suggested !== '' && $plans->contains(fn ($p) => $p->code === $suggested)
            ? $suggested
            : null;

        // PayU recurring subscription (jeśli klient wybrał PayU zamiast
        // Stripe). Pokazuje kartę + status + cancel CTA.
        $payuSubscription = Subscription::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('payu_recurring_token')
            ->whereIn('status', ['active', 'past_due'])
            ->latest()
            ->first();

        return view('tenant.billing.show', [
            'tenant' => $tenant,
            'currentPlan' => $tenant->plan,
            'plans' => $plans,
            'trialDaysLeft' => $this->trialDaysLeft($tenant),
            'hasSubscription' => $tenant->stripe_subscription_id !== null || $payuSubscription !== null,
            'suggestedPlan' => $suggestedPlan,
            'payuSubscription' => $payuSubscription,
        ]);
    }

    /**
     * Tworzy nową PayU subskrypcję + setup invoice (z onboarding_fee) i
     * redirect na hosted PayU checkout. Pierwsza płatność z recurring=FIRST
     * tokenizuje kartę → webhook → markChargeSucceeded → status=active.
     */
    public function payuCheckout(Request $request): RedirectResponse
    {
        $tenant = $this->resolveTenant();
        if ($tenant === null) {
            return redirect()->route('tenant.select');
        }
        $this->authorizeAdmin($tenant);

        $data = $request->validate([
            'plan_code' => ['required', 'string', 'max:32'],
            'period' => ['required', 'in:monthly,yearly'],
        ]);

        $plan = Plan::where('code', $data['plan_code'])
            ->where('is_active', true)
            ->first();

        if ($plan === null) {
            return back()->withErrors(['plan' => __('billing.errors.unknown_plan')]);
        }

        // Setup total = price_{cycle} + onboarding_fee. Po opłaceniu kolejne
        // cykliczne charge'y idą TYLKO za price_{cycle} (bez fee).
        $cycleCents = $data['period'] === 'yearly'
            ? (int) ($plan->price_yearly_cents ?? 0)
            : (int) ($plan->price_monthly_cents ?? 0);
        $totalCents = $cycleCents + (int) ($plan->onboarding_fee_cents ?? 0);

        if ($totalCents <= 0) {
            return back()->withErrors(['plan' => __('billing.errors.unknown_plan')]);
        }

        try {
            $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => 'incomplete',
                'billing_cycle' => $data['period'],
            ]);

            $vatRate = 23;
            $netCents = (int) round($totalCents * 100 / (100 + $vatRate));
            $invoice = Invoice::create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'number' => $this->nextInvoiceNumber(),
                'kind' => 'regular',
                'plan_code' => $plan->code,
                'period' => $data['period'],
                'currency' => (string) ($plan->currency ?? 'PLN'),
                'amount_cents' => $netCents,
                'vat_cents' => $totalCents - $netCents,
                'total_cents' => $totalCents,
                'vat_rate' => $vatRate,
                'status' => 'open',
                'issued_at' => now(),
                'due_at' => now()->addDays(14),
            ]);

            $url = app(PayUService::class)->createRecurringSetup($invoice, $subscription);
        } catch (\Throwable $e) {
            Log::error('PayU recurring setup failed', [
                'tenant_id' => $tenant->id,
                'plan_code' => $plan->code,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['payu' => __('billing.errors.checkout_failed')]);
        }

        app(TenantAuditLogger::class)->record('billing.payu_setup.start', 'Plan', $plan->id, [
            'plan_code' => $plan->code,
            'period' => $data['period'],
            'subscription_id' => $subscription->id,
        ]);

        return redirect()->away($url);
    }

    /**
     * Cancel recurring na końcu obecnego okresu — token usunięty
     * natychmiast (żadnych dalszych charge'y), ale dostęp trzyma się do
     * `current_period_end`.
     */
    public function payuCancel(Request $request): RedirectResponse
    {
        $tenant = $this->resolveTenant();
        if ($tenant === null) {
            return redirect()->route('tenant.select');
        }
        $this->authorizeAdmin($tenant);

        $subscription = Subscription::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('payu_recurring_token')
            ->whereIn('status', ['active', 'past_due'])
            ->latest()
            ->first();

        if ($subscription === null) {
            return back();
        }

        $subscription->forceFill([
            'payu_recurring_token' => null,
            'cancelled_at' => now(),
        ])->save();

        app(TenantAuditLogger::class)->record('billing.payu_cancel', 'Subscription', $subscription->id, [
            'period_end' => $subscription->current_period_end?->toIso8601String(),
        ]);

        return redirect()->route('billing.show')->with('status', __('billing.payu.cancel_success'));
    }

    public function checkout(Request $request): RedirectResponse
    {
        $tenant = $this->resolveTenant();
        if ($tenant === null) {
            return redirect()->route('tenant.select');
        }
        $this->authorizeAdmin($tenant);

        $data = $request->validate([
            'plan_code' => ['required', 'string', 'max:32'],
            'period' => ['required', 'in:monthly,yearly'],
        ]);

        $plan = Plan::where('code', $data['plan_code'])
            ->where('is_active', true)
            ->first();

        if ($plan === null) {
            return back()->withErrors(['plan' => __('billing.errors.unknown_plan')]);
        }

        try {
            $url = app(StripeBillingService::class)->createCheckoutSession($tenant, $plan, $data['period']);
        } catch (\Throwable $e) {
            Log::error('Stripe checkout creation failed', [
                'tenant_id' => $tenant->id,
                'plan_code' => $plan->code,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['stripe' => __('billing.errors.checkout_failed')]);
        }

        app(TenantAuditLogger::class)->record('billing.checkout.start', 'Plan', $plan->id, [
            'plan_code' => $plan->code,
            'period' => $data['period'],
        ]);

        return redirect()->away($url);
    }

    public function return(Request $request): RedirectResponse|ViewContract|Renderable
    {
        $tenant = $this->resolveTenant();
        if ($tenant === null) {
            return redirect()->route('tenant.select');
        }
        $this->authorizeAdmin($tenant);

        // We don't trust the success URL alone — the webhook is the
        // source of truth. Just show a "give it a moment" page that
        // refreshes the tenant from DB so any already-arrived webhook
        // is reflected immediately.
        $tenant->refresh();

        return view('tenant.billing.return', [
            'tenant' => $tenant,
            'sessionId' => $request->query('session_id'),
            'isActive' => $tenant->status === 'active' && $tenant->stripe_subscription_id !== null,
        ]);
    }

    public function portal(): RedirectResponse
    {
        $tenant = $this->resolveTenant();
        if ($tenant === null) {
            return redirect()->route('tenant.select');
        }
        $this->authorizeAdmin($tenant);

        try {
            $url = app(StripeBillingService::class)->createPortalSession($tenant);
        } catch (\Throwable $e) {
            Log::error('Stripe portal creation failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['stripe' => __('billing.errors.portal_failed')]);
        }

        return redirect()->away($url);
    }

    private function resolveTenant(): ?Tenant
    {
        $tenant = $this->tenants->current();
        if ($tenant) {
            return $tenant;
        }

        $tenantId = request()->session()->get('current_tenant_id');
        if (! is_string($tenantId)) {
            return null;
        }

        return Tenant::find($tenantId);
    }

    private function authorizeAdmin(Tenant $tenant): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        if ($user->is_master_admin === true) {
            return;
        }

        $allowed = $tenant->memberships()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->whereIn('role', ['owner', 'admin'])
            ->exists();

        abort_unless($allowed, 403);
    }

    private function trialDaysLeft(Tenant $tenant): ?int
    {
        if ($tenant->trial_ends_at === null) {
            return null;
        }

        return (int) max(0, now()->startOfDay()->diffInDays($tenant->trial_ends_at, false));
    }

    /**
     * HVR/{YYYY}/{MM}/{NNNN} — convention z StripeBillingService +
     * ChargeRecurringPayUSubscriptionsJob.
     */
    private function nextInvoiceNumber(): string
    {
        $prefix = sprintf('HVR/%s/%s/', now()->format('Y'), now()->format('m'));
        $last = Invoice::where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $next = 1;
        if (is_string($last) && $last !== '') {
            $parts = explode('/', $last);
            $tail = (int) end($parts);
            $next = max($next, $tail + 1);
        }

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
