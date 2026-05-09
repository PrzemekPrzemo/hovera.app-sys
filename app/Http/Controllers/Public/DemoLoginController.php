<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Auto-login flow for the public demo. Picks the demo tenant + its
 * configured demo owner, signs the visitor in and drops them on /app.
 *
 * Whoever lands on /demo gets the same shared session — perfect for a
 * sales pitch ("zobacz jak to wygląda") without the friction of a
 * registration form. Data is wiped + re-seeded by `hovera:demo:reset`
 * every night at 22:00, so accidental damage from one visitor is gone
 * before the next morning.
 *
 * Security: the demo user has a random unguessable password set at
 * provisioning and is excluded from the standard login form (see
 * AuthServiceProvider). The only way in is via this route.
 */
class DemoLoginController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $slug = (string) config('hovera.demo.slug', 'demo');

        $tenant = Tenant::query()->where('slug', $slug)->where('status', 'active')->first();
        if (! $tenant) {
            abort(503, 'Demo tymczasowo niedostępne — odśwież za chwilę.');
        }

        // Owner of the demo tenant becomes the auto-login target. We pick
        // the oldest non-revoked owner membership so re-seeding doesn't
        // change the target if the seeder rotates ownership later.
        $membership = TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('role', 'owner')
            ->whereNull('revoked_at')
            ->orderBy('created_at')
            ->first();

        if (! $membership) {
            abort(503, 'Demo tymczasowo niedostępne — owner nieprzypisany.');
        }

        $user = User::query()->find($membership->user_id);
        if (! $user) {
            abort(503, 'Demo tymczasowo niedostępne — konto ownera usunięte.');
        }

        // Migrate the session before login so the existing CSRF token /
        // anonymous data don't bleed across visitors. AuthenticateSession
        // will pick the new ID up on the next request.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Auth::login($user);
        $request->session()->put('current_tenant_id', $tenant->id);
        $request->session()->put('demo.is_demo', true);
        $request->session()->put('demo.expires_at', now()->addHours(2)->toIso8601String());

        return redirect('/app');
    }
}
