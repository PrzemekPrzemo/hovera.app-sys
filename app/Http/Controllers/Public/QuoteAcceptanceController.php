<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Transport\Notifications\QuoteAcceptedNotification;
use App\Domain\Transport\Notifications\QuoteRejectedNotification;
use App\Enums\QuoteStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Quote;
use App\Tenancy\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\View\View;

/**
 * Publiczna akceptacja/odrzucenie oferty przez klienta — z linku w mailu.
 * URL: /transport/quote/{slug}/{token}
 *
 *   GET   /transport/quote/{slug}/{token}            → landing z podsumowaniem
 *   POST  /transport/quote/{slug}/{token}/accept     → status=Accepted + notify owner
 *   POST  /transport/quote/{slug}/{token}/reject     → status=Rejected + notify owner
 *
 * Bez autoryzacji — token (48 znaków, generowany przy sendQuote()) jest
 * jedyną poświadczeniową. Throttle przy POST chroni przed brute-force
 * zgadywaniem tokenów.
 *
 * Patrz docs/TRANSPORT.md §9 faza 3 punkt 4.
 */
class QuoteAcceptanceController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenants,
    ) {}

    public function show(string $slug, string $token): View|Response
    {
        $quote = $this->resolveQuote($slug, $token);
        if (! $quote) {
            abort(404);
        }

        return view('public.transport.quote-landing', [
            'tenant' => $this->tenants->tenantOrFail(),
            'quote' => $quote,
            'slug' => $slug,
            'token' => $token,
        ]);
    }

    public function accept(Request $request, string $slug, string $token): RedirectResponse
    {
        $quote = $this->resolveQuote($slug, $token);
        if (! $quote) {
            abort(404);
        }

        if ($quote->status !== QuoteStatus::Sent) {
            return redirect()->route('public.transport.quote', ['slug' => $slug, 'token' => $token]);
        }

        $quote->forceFill([
            'status' => QuoteStatus::Accepted,
            'accepted_at' => now(),
        ])->save();

        // Marketplace close-out: jeśli quote powstała z lead'a (lead_id set),
        // domykamy całe zapytanie — pozostałe TransportLeadResponse → rejected,
        // notyfikacje "lead zamknięty" lecą do innych transporterów. Patrz
        // docs/TRANSPORT.md §5.3.
        app(\App\Domain\Transport\Leads\QuoteAcceptanceService::class)
            ->onQuoteAccepted($quote, $this->tenants->tenantOrFail());

        $this->notifyOwner($quote, accepted: true);

        return redirect()->route('public.transport.quote', ['slug' => $slug, 'token' => $token])
            ->with('accepted', true);
    }

    public function reject(Request $request, string $slug, string $token): RedirectResponse
    {
        $quote = $this->resolveQuote($slug, $token);
        if (! $quote) {
            abort(404);
        }

        if ($quote->status !== QuoteStatus::Sent) {
            return redirect()->route('public.transport.quote', ['slug' => $slug, 'token' => $token]);
        }

        $quote->forceFill([
            'status' => QuoteStatus::Rejected,
            'rejected_at' => now(),
        ])->save();

        $this->notifyOwner($quote, accepted: false);

        return redirect()->route('public.transport.quote', ['slug' => $slug, 'token' => $token])
            ->with('rejected', true);
    }

    /**
     * Łączy się z tenant DB po slug, szuka quote po accept_token. Cache'u
     * świadomie nie używamy — tokeny używane raz, hit/miss cache marnuje
     * pamięć bez korzyści.
     */
    private function resolveQuote(string $slug, string $token): ?Quote
    {
        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $slug)) {
            return null;
        }
        if (! preg_match('/^[A-Za-z0-9]{40,80}$/', $token)) {
            return null;
        }

        $tenant = Tenant::query()
            ->where('slug', $slug)
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->first();

        if (! $tenant) {
            return null;
        }

        $this->tenants->setCurrent($tenant);

        // Quote może być Sent / Accepted / Rejected (re-wizyta landing'a) —
        // wszystkie pokazujemy. Withdrawn / Expired / Draft = 404 (token
        // już nieważny lub jeszcze nie wysłany).
        return Quote::query()
            ->where('accept_token', $token)
            ->whereIn('status', ['sent', 'accepted', 'rejected'])
            ->first();
    }

    private function notifyOwner(Quote $quote, bool $accepted): void
    {
        $tenant = $this->tenants->tenantOrFail();
        $ownerEmail = $this->resolveOwnerEmail($tenant);
        if ($ownerEmail === null) {
            return;
        }

        $notification = $accepted
            ? new QuoteAcceptedNotification($quote)
            : new QuoteRejectedNotification($quote);

        try {
            NotificationFacade::route('mail', $ownerEmail)->notify($notification);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Email właściciela tenant'a = pierwszy aktywny membership o roli 'owner'.
     * Bez fancy fallbacku — gdy nie ma owner'a, notyfikacja po prostu nie idzie
     * (status oferty i tak jest zaktualizowany w panelu).
     */
    private function resolveOwnerEmail(Tenant $tenant): ?string
    {
        $row = DB::connection('central')
            ->table('tenant_memberships')
            ->join('users', 'tenant_memberships.user_id', '=', 'users.id')
            ->where('tenant_memberships.tenant_id', $tenant->id)
            ->where('tenant_memberships.role', 'owner')
            ->whereNull('tenant_memberships.revoked_at')
            ->select('users.email')
            ->first();

        return $row?->email;
    }
}
