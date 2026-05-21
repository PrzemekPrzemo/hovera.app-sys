<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Enums\TenantType;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Tenant selection flow after login.
 *
 *   - 0 active memberships → friendly dead-end page (could happen for
 *     a master admin who has no stable yet)
 *   - 1 active membership  → auto-select, redirect to correct panel
 *     (`/app` dla stable, `/transport` dla transporter)
 *   - >1 active membership → list, user picks
 *
 * Transporter tenant w statusie `provisioning` (świeży signup, czeka na
 * weryfikację dokumentów) też jest pokazywany — owner musi się zalogować
 * żeby śledzić status weryfikacji + ewentualnie douploadować braki.
 */
class TenantSelectorController extends Controller
{
    /**
     * Statusy tenant'a w których user może wejść do panelu — alias do
     * `Tenant::PANEL_ACCESSIBLE_STATUSES` (single source of truth).
     * `provisioning` dotyczy transporterów czekających na verification.
     */
    private const SELECTABLE_TENANT_STATUSES = Tenant::PANEL_ACCESSIBLE_STATUSES;

    public function show(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $memberships = TenantMembership::query()
            ->with(['tenant'])
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->whereHas('tenant', fn ($q) => $q->whereIn('status', self::SELECTABLE_TENANT_STATUSES))
            ->get();

        if ($memberships->isEmpty()) {
            return view('tenant.no-tenants');
        }

        if ($memberships->count() === 1) {
            $membership = $memberships->first();
            $request->session()->put('current_tenant_id', $membership->tenant_id);

            return redirect()->intended($this->panelUrlFor($membership->tenant?->type));
        }

        return view('tenant.select', compact('memberships'));
    }

    public function choose(Request $request): RedirectResponse
    {
        $request->validate([
            'tenant_id' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $membership = TenantMembership::query()
            ->with(['tenant'])
            ->where('user_id', $user->id)
            ->where('tenant_id', $request->string('tenant_id'))
            ->whereNull('revoked_at')
            ->whereHas('tenant', fn ($q) => $q->whereIn('status', self::SELECTABLE_TENANT_STATUSES))
            ->first();

        if (! $membership) {
            return back()->withErrors(['tenant_id' => __('auth.tenant_select.no_access')]);
        }

        $request->session()->put('current_tenant_id', $membership->tenant_id);

        return redirect()->intended($this->panelUrlFor($membership->tenant?->type));
    }

    public function switch(Request $request): RedirectResponse
    {
        $request->session()->forget('current_tenant_id');

        return redirect()->route('tenant.select');
    }

    /**
     * Stable → /app, Transporter → /transport. Null fallback do /app
     * (legacy tenant'y bez typu dostają stable-panel jak dotychczas).
     */
    private function panelUrlFor(?TenantType $type): string
    {
        return '/'.($type?->panelId() ?? 'app');
    }
}
