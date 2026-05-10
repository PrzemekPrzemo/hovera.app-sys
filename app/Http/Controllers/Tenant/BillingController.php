<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
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
        private readonly StripeBillingService $billing,
        private readonly TenantManager $tenants,
    ) {}

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

        return view('tenant.billing.show', [
            'tenant' => $tenant,
            'currentPlan' => $tenant->plan,
            'plans' => $plans,
            'trialDaysLeft' => $this->trialDaysLeft($tenant),
            'hasSubscription' => $tenant->stripe_subscription_id !== null,
            'suggestedPlan' => $suggestedPlan,
        ]);
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
            $url = $this->billing->createCheckoutSession($tenant, $plan, $data['period']);
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
            $url = $this->billing->createPortalSession($tenant);
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
}
