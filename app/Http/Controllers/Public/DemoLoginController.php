<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Models\Tenant\Client;
use App\Services\Portal\ClientPortalAuth;
use App\Tenancy\TenantManager;
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
    /** Roles available via /demo/as/{role} for the in-panel role switcher. */
    public const SWITCHABLE_ROLES = ['owner', 'admin', 'manager', 'instructor', 'employee', 'vet', 'viewer'];

    public function __construct(
        private readonly TenantManager $tenants,
        private readonly ClientPortalAuth $portalAuth,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        return $this->loginAs($request, 'owner', regenerate: true);
    }

    /**
     * Role switcher for the demo banner — re-logins the visitor as a
     * different demo user (manager / trener / pracownik / vet / viewer)
     * to showcase the role-based visibility matrix without leaving demo.
     *
     * Only allowed when the current session already has demo.is_demo set;
     * otherwise this would be a free auth bypass on production.
     */
    public function switchRole(Request $request, string $role): RedirectResponse
    {
        if ($request->session()->get('demo.is_demo') !== true) {
            abort(404);
        }

        if (! in_array($role, self::SWITCHABLE_ROLES, true)) {
            abort(404);
        }

        return $this->loginAs($request, $role, regenerate: false);
    }

    /**
     * Loguje zwiedzającego /demo do panelu klienta (portal właściciela konia)
     * jako sample client. Bez magic-link, bez emaila — od razu na dashboard
     * `/s/demo/portal`. Pokazuje "po drugiej stronie barykady": rezerwacje,
     * konie, faktury, wiadomości z perspektywy jeźdźca/właściciela.
     *
     * Bezpieczeństwo: wymaga session('demo.is_demo') = true (czyli użytkownik
     * najpierw wszedł na /demo i zalogował się przynajmniej jako owner) —
     * inaczej 404, żeby nie udostępniać auth bypass na klienta.
     */
    public function loginAsClient(Request $request): RedirectResponse
    {
        if ($request->session()->get('demo.is_demo') !== true) {
            abort(404);
        }

        $slug = (string) config('hovera.demo.slug', 'demo');

        $tenant = Tenant::query()
            ->withTrashed()
            ->where('slug', $slug)
            ->whereIn('status', ['active', 'trialing'])
            ->first();
        if (! $tenant) {
            abort(503, 'Demo tymczasowo niedostępne — odśwież za chwilę.');
        }
        if ($tenant->trashed()) {
            $tenant->restore();
        }

        // Klienci siedzą w tenant DB — switch contextu, wybierz pierwszego
        // z ćwiczeniem (ma bookings + horse) żeby dashboard nie był pusty.
        $client = $this->tenants->execute($tenant, function () {
            return Client::query()
                ->whereHas('horses')
                ->orderBy('created_at')
                ->first()
                ?? Client::query()->orderBy('created_at')->first();
        });

        if (! $client instanceof Client) {
            abort(503, 'Demo tymczasowo niedostępne — brak przykładowego klienta.');
        }

        // Wyloguj z panelu Filament (jeśli aktywny) żeby nie kolidowało
        // z guard'em portalu — portal trzyma własną sekcję sesji per-tenant.
        Auth::logout();
        $this->portalAuth->login($request, $client, $slug);

        $request->session()->put('demo.is_demo', true);
        $request->session()->put('demo.role', 'client');
        $request->session()->put('demo.expires_at', now()->addHours(2)->toIso8601String());

        return redirect()->route('client_portal.dashboard', ['slug' => $slug]);
    }

    private function loginAs(Request $request, string $role, bool $regenerate): RedirectResponse
    {
        $slug = (string) config('hovera.demo.slug', 'demo');

        // Allow trialing too — demo tenant is provisioned by hovera:demo:seed
        // and lives forever in trial state (we never put a Stripe sub on it).
        // Filtering only on 'active' would 503 immediately after every reset.
        //
        // withTrashed + auto-restore — żeby przypadkowy soft-delete (np. master
        // admin klika "Usuń" w /admin/tenants) nie ubijał /demo na stałe.
        // Demo to publiczny endpoint sprzedażowy, ma być nieubijalny.
        $tenant = Tenant::query()
            ->withTrashed()
            ->where('slug', $slug)
            ->whereIn('status', ['active', 'trialing'])
            ->first();
        if (! $tenant) {
            abort(503, 'Demo tymczasowo niedostępne — odśwież za chwilę.');
        }
        if ($tenant->trashed()) {
            $tenant->restore();
        }

        $membership = TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('role', $role)
            ->whereNull('revoked_at')
            ->orderBy('created_at')
            ->first();

        if (! $membership) {
            abort(503, "Demo tymczasowo niedostępne — brak konta o roli '{$role}'.");
        }

        $user = User::query()->find($membership->user_id);
        if (! $user) {
            abort(503, 'Demo tymczasowo niedostępne — konto użytkownika usunięte.');
        }

        // Pierwszy entry-point regeneruje sesję od nowa (CSRF token, session
        // ID). Role switch wewnątrz demo zachowuje sesję, tylko zmienia
        // auth user i flagę roli.
        if ($regenerate) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } else {
            Auth::logout();
            $request->session()->regenerate();
        }

        Auth::login($user);

        // AuthenticateSession middleware porównuje password_hash_web w sesji
        // z $user->getAuthPassword() przy każdym requeście. Auth::logout()
        // NIE czyści tego klucza, więc po Auth::login(newUser) hash w sesji
        // zostaje stary i następny request nas wyloguje → redirect na
        // /app/login. Wymuszamy poprawny hash dla nowego usera.
        $guard = (string) config('auth.defaults.guard', 'web');
        $request->session()->put('password_hash_'.$guard, $user->getAuthPassword());

        $request->session()->put('current_tenant_id', $tenant->id);
        $request->session()->put('demo.is_demo', true);
        $request->session()->put('demo.role', $role);
        $request->session()->put('demo.expires_at', now()->addHours(2)->toIso8601String());

        return redirect('/app');
    }
}
