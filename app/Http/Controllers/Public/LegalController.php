<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Statyczne strony prawne wymagane do compliance:
 *
 *   /regulamin                       Regulamin świadczenia usługi (PL law).
 *   /polityka-prywatnosci             Polityka prywatności (RODO art. 13).
 *   /dpa                              Data Processing Agreement (RODO art. 28),
 *                                     stajnia = administrator danych klientów,
 *                                     hovera = procesor.
 *   /regulamin-marketplace            Regulamin marketplace transportowego —
 *                                     hovera = pośrednik (NIE przewoźnik),
 *                                     umowa przewozu zawierana bezpośrednio
 *                                     między Klientem a Przewoźnikiem.
 *
 * Treść w lang/{pl,en,de,fr,ru}/public/legal.php — łatwo zmienić bez
 * deployu widoku, łatwo przetłumaczyć. Każdy plik lang ma `last_updated`
 * (ISO date) renderowany na górze strony.
 */
class LegalController extends Controller
{
    public function terms(): View
    {
        return view('public.legal.terms');
    }

    public function privacy(): View
    {
        return view('public.legal.privacy');
    }

    public function dpa(): View
    {
        return view('public.legal.dpa');
    }

    public function marketplace(): View
    {
        return view('public.legal.marketplace');
    }
}
