<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

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
 *   - 1 active membership  → auto-select, redirect to /app
 *   - >1 active membership → list, user picks
 */
class TenantSelectorController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $memberships = TenantMembership::query()
            ->with(['tenant'])
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->whereHas('tenant', fn ($q) => $q->whereIn('status', ['trialing', 'active', 'past_due']))
            ->get();

        if ($memberships->isEmpty()) {
            return view('tenant.no-tenants');
        }

        if ($memberships->count() === 1) {
            $request->session()->put('current_tenant_id', $memberships->first()->tenant_id);

            return redirect()->intended('/app');
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
            ->where('user_id', $user->id)
            ->where('tenant_id', $request->string('tenant_id'))
            ->whereNull('revoked_at')
            ->whereHas('tenant', fn ($q) => $q->whereIn('status', ['trialing', 'active', 'past_due']))
            ->first();

        if (! $membership) {
            return back()->withErrors(['tenant_id' => 'Brak dostępu do wybranej stajni.']);
        }

        $request->session()->put('current_tenant_id', $membership->tenant_id);

        return redirect()->intended('/app');
    }

    public function switch(Request $request): RedirectResponse
    {
        $request->session()->forget('current_tenant_id');

        return redirect()->route('tenant.select');
    }
}
