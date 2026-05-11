<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active UI locale on every web request.
 *
 * Priority:
 *   1. ?lang=xx query param (one-shot override → also persisted to session)
 *   2. session('locale')                              (set by switcher)
 *   3. Auth::user()->locale                           (per-user preference)
 *   4. config('app.locale')                           (env default)
 */
class SetLocale
{
    private const SUPPORTED = ['pl', 'en', 'fr', 'de', 'ru'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        App::setLocale($locale);

        if ($request->hasSession()) {
            $request->session()->put('locale', $locale);
        }

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        $candidate = $request->query('lang');
        if (is_string($candidate) && in_array($candidate, self::SUPPORTED, true)) {
            return $candidate;
        }

        if ($request->hasSession()) {
            $session = $request->session()->get('locale');
            if (is_string($session) && in_array($session, self::SUPPORTED, true)) {
                return $session;
            }
        }

        $userLocale = Auth::user()?->locale;
        if (is_string($userLocale) && in_array($userLocale, self::SUPPORTED, true)) {
            return $userLocale;
        }

        return (string) config('app.locale', 'pl');
    }
}
