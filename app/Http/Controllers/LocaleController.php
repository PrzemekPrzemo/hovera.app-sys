<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Central\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class LocaleController extends Controller
{
    private const SUPPORTED = ['pl', 'en'];

    /**
     * Sets the UI locale (session + per-user preference if logged in)
     * and redirects back. SetLocale middleware reads from the same
     * session key on subsequent requests.
     */
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        if (! in_array($locale, self::SUPPORTED, true)) {
            return back();
        }

        $request->session()->put('locale', $locale);

        /** @var User|null $user */
        $user = Auth::user();
        if ($user && $user->locale !== $locale) {
            $user->forceFill(['locale' => $locale])->save();
        }

        return back();
    }
}
