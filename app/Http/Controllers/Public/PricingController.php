<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Models\Central\Plan;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Public pricing page — `/pricing`. Reads plans from central DB
 * (filtered to is_public=true, is_active=true) and renders Hovera-branded
 * comparison table with monthly/yearly toggle.
 *
 * USP: wszyscy konkurenci PL (Nasza Stajnia, Horstable, Redini, Equita)
 * ukrywają cennik za formularzem kontaktowym. My pokazujemy.
 */
class PricingController extends Controller
{
    public function show(): View
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->get();

        return view('public.pricing.index', [
            'plans' => $plans,
        ]);
    }
}
