<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Central\MasterAd;
use App\Services\Ads\MasterAdResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class MasterAdController extends Controller
{
    public function __construct(private readonly MasterAdResolver $resolver) {}

    public function dismiss(Request $request, MasterAd $ad): RedirectResponse
    {
        $user = Auth::user();
        if ($user) {
            $this->resolver->dismiss($user, (string) $ad->id);
        }

        return back();
    }

    public function click(Request $request, MasterAd $ad): RedirectResponse
    {
        $this->resolver->trackClick($ad);

        $url = $ad->cta_url ?: '/';

        return redirect()->away($url);
    }
}
