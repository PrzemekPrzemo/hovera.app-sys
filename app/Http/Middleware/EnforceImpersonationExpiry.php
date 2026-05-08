<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Impersonation\StopImpersonation;
use App\Support\ImpersonationDebug;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * If an impersonation session has expired, end it before the request
 * proceeds. Runs early in the /app middleware stack.
 */
class EnforceImpersonationExpiry
{
    public function __construct(private readonly StopImpersonation $stop) {}

    public function handle(Request $request, Closure $next): Response
    {
        $expiresAt = $request->session()->get('impersonation.expires_at');

        if ($expiresAt) {
            ImpersonationDebug::snap('5_app_request_during_impersonation', [
                'expires_at' => $expiresAt,
                'is_expired' => Carbon::parse($expiresAt)->isPast(),
            ]);
        }

        if ($expiresAt && Carbon::parse($expiresAt)->isPast()) {
            $this->stop->execute($request->session());

            return redirect('/'.config('hovera.admin.path'))
                ->with('status', 'Sesja impersonacji wygasła i została zakończona.');
        }

        return $next($request);
    }
}
