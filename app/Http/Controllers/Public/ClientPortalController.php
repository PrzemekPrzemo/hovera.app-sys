<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\CalendarEntryStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\CalendarEntry;
use App\Models\Tenant\Client;
use App\Notifications\ClientPortalMagicLinkNotification;
use App\Services\Calendar\BookingCancellationLink;
use App\Services\Portal\ClientPortalAuth;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class ClientPortalController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenants,
        private readonly ClientPortalAuth $auth,
        private readonly BookingCancellationLink $cancelLinks,
        private readonly TenantAuditLogger $audit,
    ) {}

    public function showLogin(Request $request, string $slug): View|RedirectResponse
    {
        $tenant = $this->resolveAndActivate($slug);

        if ($this->auth->current($request, $slug)) {
            return redirect()->route('client_portal.dashboard', ['slug' => $slug]);
        }

        return view('public.portal.login', [
            'tenant' => $tenant,
            'primary_color' => data_get($tenant->branding, 'primary_color', '#10b981'),
        ]);
    }

    /**
     * Always responds with the same "we sent a link if the email is
     * registered" page — never disclose whether an email is known.
     */
    public function submitLogin(Request $request, string $slug): View
    {
        $tenant = $this->resolveAndActivate($slug);

        $data = $request->validate([
            'email' => ['required', 'email:rfc,strict'],
        ]);

        $client = Client::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($data['email'])])
            ->first();

        if ($client) {
            $url = $this->auth->issueMagicLink($client, $slug);

            Notification::route('mail', $client->email)->notify(
                new ClientPortalMagicLinkNotification(
                    tenantName: $tenant->name,
                    magicLinkUrl: $url,
                    ttlMinutes: ClientPortalAuth::TOKEN_TTL_MINUTES,
                ),
            );

            $this->audit->record('client_portal.magic_link_sent', 'Client', (string) $client->id);
        }

        return view('public.portal.login-sent', [
            'tenant' => $tenant,
            'email' => $data['email'],
            'primary_color' => data_get($tenant->branding, 'primary_color', '#10b981'),
        ]);
    }

    public function consumeLogin(Request $request, string $slug, string $clientId): View|RedirectResponse
    {
        $tenant = $this->resolveAndActivate($slug);

        $token = (string) $request->query('token', '');
        $client = Client::query()->find($clientId);

        $valid = $client !== null
            && $token !== ''
            && $this->auth->consume($request, $client, $token, $slug);

        if (! $valid) {
            return view('public.portal.login-invalid', [
                'tenant' => $tenant,
                'primary_color' => data_get($tenant->branding, 'primary_color', '#10b981'),
            ]);
        }

        $this->audit->record('client_portal.logged_in', 'Client', (string) $client->id);

        return redirect()->route('client_portal.dashboard', ['slug' => $slug]);
    }

    public function logout(Request $request, string $slug): RedirectResponse
    {
        $this->resolveAndActivate($slug);
        $this->auth->logout($request, $slug);

        return redirect()->route('client_portal.login.show', ['slug' => $slug]);
    }

    public function dashboard(Request $request, string $slug): View|RedirectResponse
    {
        $tenant = $this->resolveAndActivate($slug);
        $client = $this->auth->current($request, $slug);

        if (! $client) {
            return redirect()->route('client_portal.login.show', ['slug' => $slug]);
        }

        $now = now();

        $upcoming = CalendarEntry::query()
            ->with(['instructor', 'horse', 'arena'])
            ->where('client_id', $client->id)
            ->whereIn('status', [
                CalendarEntryStatus::Requested->value,
                CalendarEntryStatus::Confirmed->value,
            ])
            ->where('starts_at', '>=', $now)
            ->orderBy('starts_at')
            ->limit(50)
            ->get();

        $past = CalendarEntry::query()
            ->with(['instructor', 'horse', 'arena'])
            ->where('client_id', $client->id)
            ->where('starts_at', '<', $now)
            ->orderByDesc('starts_at')
            ->limit(20)
            ->get();

        $cancelLinks = $upcoming
            ->filter(fn (CalendarEntry $e) => $e->status === CalendarEntryStatus::Confirmed)
            ->mapWithKeys(fn (CalendarEntry $e) => [
                $e->id => $this->cancelLinks->for($e, $tenant->slug),
            ]);

        return view('public.portal.dashboard', [
            'tenant' => $tenant,
            'client' => $client,
            'upcoming' => $upcoming,
            'past' => $past,
            'cancel_links' => $cancelLinks,
            'primary_color' => data_get($tenant->branding, 'primary_color', '#10b981'),
        ]);
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
