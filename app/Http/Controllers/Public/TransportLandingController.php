<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Transport\Public\TransporterRankingService;
use App\Enums\TenantType;
use App\Models\Central\TenantMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Publiczny landing `/transport` — entry point dla niezalogowanych klientów
 * (przewoźnicy, którzy chcą zlecić transport koni). Pokazuje:
 *   - hero z claim'em + embed pełnego formularza zapytania (broadcast lead)
 *   - „Top 10 zweryfikowanych przewoźników" z featured boost'em
 *   - CTA do katalogu /przewoznicy
 *
 * Auth-aware: zalogowani transporterzy są przekierowywani do panelu (żeby
 * przypadkowe wpisanie `/transport` w pasku nie ginęło na landingu zamiast
 * dashboard'u). Zalogowani stable owie — do `/transport/zapytanie?stable={ulid}`
 * z pre-fill kontekstu stajni.
 *
 * URL koliduje logicznie z Filament panel'em (`TransportPanelProvider.path`
 * = `transport`), ale Laravel router rejestruje publiczne trasy z
 * `routes/web.php` przed Filament resolverem, więc exact `/transport`
 * trafia tutaj. Sub-route'y panelu (`/transport/quotes`, `/transport/leads`)
 * pozostają nietknięte. Patrz docs/TRANSPORT.md §16.
 */
class TransportLandingController extends Controller
{
    public function __construct(private readonly TransporterRankingService $ranking) {}

    public function show(Request $request): View|RedirectResponse
    {
        // Auth-aware redirect — gdy user jest zalogowany i ma kontekst,
        // landing nie ma dla niego value. Anonimowi i out-of-context dostają
        // pełną stronę.
        if (Auth::check()) {
            $redirect = $this->redirectForAuthenticated();
            if ($redirect !== null) {
                return $redirect;
            }
        }

        $topTransporters = $this->ranking->top(10);

        return view('public.transport.landing', [
            'topTransporters' => $topTransporters,
            // Defaulty pre-fillu formularza identyczne jak na /transport/zapytanie
            // dla guesta. Direct mode dostępny przez `?transporter={slug}` w URL —
            // jeśli ktoś klika landing przez taki link, traktujemy to jak warm
            // direct lead (formularz na landingu też wspiera hidden).
            'targetTransporter' => null,
            'old' => [
                'customer_name' => (string) old('customer_name', ''),
                'customer_email' => (string) old('customer_email', ''),
                'customer_phone' => (string) old('customer_phone', ''),
                'pickup_address' => (string) old('pickup_address', ''),
                'dropoff_address' => (string) old('dropoff_address', ''),
                'preferred_date' => (string) old('preferred_date', now()->addDays(7)->toDateString()),
                'preferred_time' => (string) old('preferred_time', ''),
                'horse_count' => (int) old('horse_count', 1),
                'notes' => (string) old('notes', ''),
            ],
        ]);
    }

    /**
     * Zwraca redirect dla zalogowanego usera lub `null` jeśli landing powinien
     * być renderowany normalnie.
     *
     * - Master admin → bez redirectu (mogą chcieć zobaczyć landing publiczny).
     * - Transporter (single membership) → panel `/transport`.
     * - Stable (single membership) → `/transport/zapytanie?stable={ulid}` z pre-fillem.
     * - Multi-tenant user lub niezdecydowany kontekst → bez redirectu, normalna landing.
     */
    private function redirectForAuthenticated(): ?RedirectResponse
    {
        $user = Auth::user();
        if ($user === null) {
            return null;
        }

        $memberships = TenantMembership::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->with('tenant')
            ->get();

        if ($memberships->count() === 0) {
            // Master admin albo user bez tenancy — pokazujemy landing.
            return null;
        }

        // Tylko jeden tenant — możemy bezpiecznie przekierować w jego kontekst.
        if ($memberships->count() === 1) {
            $tenant = $memberships->first()?->tenant;
            if ($tenant === null) {
                return null;
            }

            if ($tenant->type === TenantType::Transporter) {
                // Panel home transportera — custom TransportDashboard mountowany
                // pod `/transport/dashboard` (slug). Hero CTA grid z Calculator
                // / Leads / Quotes / Invoices + onboarding checklist + widgety
                // KPI. Patrz PR #267.
                //
                // NIE redirectuj na samo `/transport` — to publiczny landing
                // (TransportLandingController), wpadlibyśmy z powrotem tutaj.
                return redirect('/transport/dashboard');
            }

            if ($tenant->type === TenantType::Stable) {
                return redirect()->to('/transport/zapytanie?stable='.$tenant->id);
            }
        }

        // Wielu tenantów → user musi wybrać kontekst (tenant switcher). Landing
        // jest neutralny, renderujemy bez redirectu.
        return null;
    }
}
