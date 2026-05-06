<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Impersonation\StopImpersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Impersonation `start` is dispatched directly from the Filament action
 * in MembershipsRelationManager (it has access to the request session
 * and Livewire form data already). This controller only handles `stop`,
 * which is wired to the banner button in /app.
 */
class ImpersonationController extends Controller
{
    public function stop(Request $request, StopImpersonation $action): RedirectResponse
    {
        $result = $action->execute($request->session());

        if ($result['returned_to']) {
            return redirect('/'.config('hovera.admin.path'))
                ->with('status', 'Wróciłeś do swojego konta master admina.');
        }

        return redirect()->route('login');
    }
}
