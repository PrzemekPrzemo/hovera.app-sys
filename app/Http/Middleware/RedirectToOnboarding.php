<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\TenantType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Po pierwszym logowaniu w danym tenant'cie redirect na wizard
 * onboardingu (per panel). Wizard `mount()` ustawia automatycznie
 * `settings.onboarding.deferred_at` → od kolejnego requestu middleware
 * NIE redirectuje już (user ma pełen dostęp do systemu).
 *
 * Trzy stany w settings.onboarding:
 *   deferred_at — wizard widziany (silent mount); user może nawigować
 *   skipped_at  — user kliknął "Pomiń wizard"
 *   completed_at — user przeszedł wszystkie kroki + Finish
 *
 * Jakiekolwiek z 3 (wasOnboardingShown) → przepuszcza.
 *
 * Master admin (is_master_admin) zawsze przepuszczany — impersonation
 * debug nie powinien być blokowany przez wizard.
 *
 * Działa za `InitialiseTenantFromSession` (potrzebuje hydrated $tenant).
 * Wpięty w `authMiddleware()` każdego z 3 paneli (App / Transport / Owner).
 *
 * Nie redirectuje gdy URL już jest na wizard'zie (anti-loop):
 *   /app/onboarding-wizard, /transport/onboarding-wizard, /owner/onboarding-wizard.
 */
class RedirectToOnboarding
{
    /** @var array<string,string> Mapuje TenantType→URL wizard'a. */
    private const WIZARD_URLS = [
        'stable' => '/app/onboarding-wizard',
        'transporter' => '/transport/onboarding-wizard',
        'horse_owner' => '/owner/onboarding-wizard',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->attributes->get('tenant');
        if (! $tenant) {
            return $next($request);
        }

        $user = $request->user();
        if ($user && $user->is_master_admin) {
            return $next($request);
        }

        // Wizard już raz widziany (deferred/skipped/completed) → middleware
        // przepuszcza, user pracuje normalnie. Banner na dashboardzie + link
        // w sidebar przypomina że można dokończyć.
        if ($tenant->wasOnboardingShown()) {
            return $next($request);
        }

        $type = $tenant->type instanceof TenantType ? $tenant->type->value : null;
        $wizardUrl = self::WIZARD_URLS[$type] ?? null;
        if ($wizardUrl === null) {
            return $next($request);
        }

        // Anti-loop: gdy user JUŻ jest na wizard'zie → przepuść
        // (inaczej infinite redirect aż user nie skończy).
        if (str_starts_with('/'.ltrim($request->path(), '/'), $wizardUrl)) {
            return $next($request);
        }

        // Filament wewnętrzne assety / livewire endpoints — nie redirectuj.
        if ($request->is('livewire/*', '*/livewire/*', '*.js', '*.css', '*.png', '*.svg')) {
            return $next($request);
        }

        return redirect($wizardUrl);
    }
}
