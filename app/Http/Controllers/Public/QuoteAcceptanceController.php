<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Transport\Leads\QuoteAcceptanceService;
use App\Domain\Transport\Notifications\QuoteAcceptedNotification;
use App\Domain\Transport\Notifications\QuoteRejectedNotification;
use App\Enums\QuoteStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Quote;
use App\Models\Tenant\TransportSettings;
use App\Services\CompanyLookup\CompanyLookupService;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

        // Direct-charge payments MVP — settings dla fallback'u (payment_instructions
        // gdy quote.payment_url nie ustawione). Patrz docs/TRANSPORT.md §15.
        // Try/catch: w starszych tenant DB tabela `transport_settings` może
        // jeszcze nie istnieć (migracja wjedzie z merge'm) — wtedy fallback do
        // null, sekcja płatności po prostu pokaże "skontaktuj się z przewoźnikiem".
        try {
            $transportSettings = TransportSettings::current();
        } catch (\Throwable) {
            $transportSettings = null;
        }

        return view('public.transport.quote-landing', [
            'tenant' => $this->tenants->tenantOrFail(),
            'quote' => $quote,
            'slug' => $slug,
            'token' => $token,
            'transportSettings' => $transportSettings,
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

        // Klient może zaakceptować ofertę jako FV firmowa (potrzebne NIP +
        // nazwa + adres do snapshot'u na FV przez KSeF). Pola opcjonalne — gdy
        // niewypełnione, FV idzie na osobę prywatną (customer_name z quote).
        $buyerData = $this->validateBuyerData($request);

        $fillData = [
            'status' => QuoteStatus::Accepted,
            'accepted_at' => now(),
        ];
        if ($buyerData['buyer_type'] === 'company') {
            $fillData['customer_company'] = $buyerData['customer_company'];
            $fillData['customer_tax_id'] = $buyerData['customer_tax_id'];
            $fillData['customer_address'] = $buyerData['customer_address'];
        }

        $quote->forceFill($fillData)->save();

        // Marketplace close-out: jeśli quote powstała z lead'a (lead_id set),
        // domykamy całe zapytanie — pozostałe TransportLeadResponse → rejected,
        // notyfikacje "lead zamknięty" lecą do innych transporterów. Patrz
        // docs/TRANSPORT.md §5.3.
        app(QuoteAcceptanceService::class)
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
     * AJAX lookup NIP-u przez GUS/CEIDG/KRS — używany na landing'u
     * gdy klient akceptuje ofertę jako firma. Token chroni endpoint:
     * tylko ktoś z linkiem widzi tę ofertę i może zrobić lookup. Throttle
     * 30/min (10/min by zawiódł podczas wpisywania paru NIP-ów pod rząd).
     */
    public function lookupNip(Request $request, string $slug, string $token): JsonResponse
    {
        $quote = $this->resolveQuote($slug, $token);
        if (! $quote) {
            return new JsonResponse(['ok' => false, 'error' => 'not_found'], 404);
        }

        $nip = (string) $request->input('nip', '');
        if (! CompanyLookupService::isValidNip($nip)) {
            return new JsonResponse(['ok' => false, 'error' => 'invalid_nip'], 422);
        }

        $data = app(CompanyLookupService::class)->lookupByNip($nip);
        if ($data === null) {
            return new JsonResponse(['ok' => false, 'error' => 'not_found'], 200);
        }

        $street = trim(
            ($data['street'] ?? '').' '
            .($data['building'] ?? '')
            .($data['apartment'] ? '/'.$data['apartment'] : '')
        );
        $addressLine = trim($street
            .($street !== '' && ! empty($data['postal_code']) ? ', ' : '')
            .(string) ($data['postal_code'] ?? '').' '
            .(string) ($data['city'] ?? ''));

        return new JsonResponse([
            'ok' => true,
            'name' => (string) ($data['name'] ?? ''),
            'address' => $addressLine,
            'sources' => array_map('strtoupper', (array) ($data['sources'] ?? [])),
        ]);
    }

    /**
     * @return array{buyer_type:string,customer_company:?string,customer_tax_id:?string,customer_address:?string}
     */
    private function validateBuyerData(Request $request): array
    {
        $validated = $request->validate([
            'buyer_type' => ['nullable', Rule::in(['private', 'company'])],
            'customer_company' => ['nullable', 'string', 'max:255'],
            'customer_tax_id' => ['nullable', 'string', 'max:32'],
            'customer_address' => ['nullable', 'string', 'max:1000'],
        ]);

        $type = $validated['buyer_type'] ?? 'private';
        if ($type === 'company') {
            $request->validate([
                'customer_company' => ['required', 'string', 'max:255'],
                'customer_tax_id' => ['required', 'string', 'max:32'],
                'customer_address' => ['required', 'string', 'max:1000'],
            ]);
            // Defence-in-depth: NIP musi być poprawny (suma kontrolna).
            // Walidacja Laravel'a nie zna polskich NIP-ów, więc dorzucamy
            // explicit check tu — błędny NIP = ValidationException 422.
            if (! CompanyLookupService::isValidNip((string) $validated['customer_tax_id'])) {
                throw ValidationException::withMessages([
                    'customer_tax_id' => [__('transport/landing.company.invalid_nip')],
                ]);
            }
        }

        return [
            'buyer_type' => $type,
            'customer_company' => $validated['customer_company'] ?? null,
            'customer_tax_id' => $validated['customer_tax_id'] ?? null,
            'customer_address' => $validated['customer_address'] ?? null,
        ];
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
