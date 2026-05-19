<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Models\Central\Tenant;
use App\Models\Central\TransportLead;
use App\Models\Central\TransportLeadResponse;
use App\Models\Central\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Publiczny portal klienta dla pojedynczego leada — dostęp przez permanent
 * link `/transport/zapytanie/portal/{slug}` (UUID z `transport_leads.access_slug`).
 *
 * Klient po wypełnieniu `/transport/zapytanie` dostaje email z tym linkiem.
 * Strona pokazuje:
 *   - podsumowanie zapytania
 *   - listę napływających ofert od przewoźników (transport_lead_responses)
 *   - CTA „Załóż konto żeby widzieć historię"
 *
 * Brak auth gate — slug to UUID v4, dostęp do dokładnie tego leada. Revoke
 * przez `access_revoked_at` ustawione w admin'ie. Nie pokazujemy listy
 * innych leadów ani danych innych klientów.
 *
 * Opt-in account creation:
 *   - Form na podstronie /signup z polem hasła (email pre-fill z lead'a)
 *   - Submit tworzy central User, backfill'uje originator_user_id na
 *     wszystkich leadach tego maila, auto-login, redirect na
 *     /transport/moje-zapytania.
 *   - Po login klient widzi historię wszystkich swoich leadów (po
 *     originator_user_id LUB originator_email match).
 */
class TransportLeadPortalController extends Controller
{
    public function show(Request $request, string $slug): View
    {
        $lead = $this->resolveLead($slug);

        // Lazy-load transporter info dla każdej oferty — central'na tabela
        // `transport_lead_responses` ma `transporter_tenant_id`, my chcemy
        // też nazwę firmy żeby klient widział kto wystawia ofertę.
        $responses = TransportLeadResponse::query()
            ->where('lead_id', $lead->id)
            ->whereIn('status', ['pending', 'accepted'])
            ->orderBy('price_gross')
            ->get();

        $transporters = Tenant::query()
            ->whereIn('id', $responses->pluck('transporter_tenant_id')->unique())
            ->get()
            ->keyBy('id');

        $existingUser = User::query()->where('email', $lead->originator_email)->first();
        $isLoggedInAsLeadOwner = Auth::check() && Auth::user()->email === $lead->originator_email;

        return view('public.transport.lead-portal', [
            'lead' => $lead,
            'responses' => $responses,
            'transporters' => $transporters,
            'accountExists' => $existingUser !== null,
            'isLoggedInAsLeadOwner' => $isLoggedInAsLeadOwner,
        ]);
    }

    public function signupForm(Request $request, string $slug): View
    {
        $lead = $this->resolveLead($slug);

        // Account już istnieje → przekieruj na portal z banner'em zamiast
        // pokazywać form (zapobiega duplikatom + ułatwia recovery).
        if (User::query()->where('email', $lead->originator_email)->exists()) {
            // Pokaż info: konto istnieje, zaloguj się normalnie. Render osobny
            // szkic bo signup nie ma sensu.
            return view('public.transport.lead-signup-exists', ['lead' => $lead]);
        }

        return view('public.transport.lead-signup', ['lead' => $lead]);
    }

    public function signupSubmit(Request $request, string $slug): RedirectResponse
    {
        $lead = $this->resolveLead($slug);

        $email = (string) $lead->originator_email;
        if ($email === '') {
            // Lead bez emailu (legacy) — nie można zarejestrować, fallback do portalu.
            return redirect()->route('public.transport.lead_portal', ['slug' => $slug]);
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:128', 'confirmed'],
            'terms' => ['accepted'],
            // Honeypot — boty wypełniają, prawdziwy user zostawia puste.
            'website' => ['nullable', 'string', Rule::in([''])],
        ], [
            'terms.accepted' => __('public/transport_lead_portal.signup_form.errors.terms'),
            'website.in' => __('public/transport_lead_portal.signup_form.errors.honeypot'),
        ]);

        // Defensive double-check race: ktoś mógł zarejestrować konto między
        // GET formularza a POST'em submit'u. Returnuj na exists page.
        if (User::query()->where('email', $email)->exists()) {
            return redirect()->route('public.transport.lead_portal.signup', ['slug' => $slug]);
        }

        $user = DB::connection('central')->transaction(function () use ($email, $lead, $data) {
            $user = User::create([
                'email' => $email,
                'name' => (string) ($lead->originator_name ?? $email),
                'password' => Hash::make($data['password']),
                'locale' => app()->getLocale(),
                'timezone' => 'Europe/Warsaw',
                'is_master_admin' => false,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            // Backfill originator_user_id na wszystkie leady z tego maila.
            // Klient odzyskuje historię która "powstała przed założeniem konta".
            TransportLead::query()
                ->where('originator_email', $email)
                ->whereNull('originator_user_id')
                ->update(['originator_user_id' => $user->id]);

            return $user;
        });

        Auth::login($user, remember: true);

        return redirect()->route('public.transport.my_inquiries')
            ->with('status', __('public/transport_lead_portal.signup_form.created'));
    }

    public function myInquiries(Request $request): View
    {
        $user = Auth::user();

        $leads = TransportLead::query()
            ->where(function ($q) use ($user) {
                $q->where('originator_user_id', $user->id)
                    ->orWhere('originator_email', $user->email);
            })
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return view('public.transport.my-inquiries', [
            'leads' => $leads,
        ]);
    }

    private function resolveLead(string $slug): TransportLead
    {
        if (! preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $slug)) {
            throw new NotFoundHttpException;
        }

        $lead = TransportLead::query()->where('access_slug', $slug)->first();

        if ($lead === null || ! $lead->isPortalAccessible()) {
            throw new NotFoundHttpException;
        }

        return $lead;
    }
}
