<?php

declare(strict_types=1);

namespace App\Http\Controllers\Transport;

use App\Domain\Transport\Payments\Stripe\TransporterStripeConnectService;
use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Services\Tenancy\TenantRoleGate;
use App\Tenancy\TenantManager;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Onboarding + return flow dla Stripe Connect Express (transporter).
 * Patrz docs/TRANSPORT.md §15.6.
 *
 * Flow:
 *   1. Transporter klika „Połącz konto Stripe" w /transport/settings
 *   2. → /transport/stripe/connect/onboard → service tworzy konto (jeśli
 *        nie istnieje) + AccountLink → 302 do Stripe (KYC u Stripe)
 *   3. Po finiszu KYC Stripe → /transport/stripe/connect/return
 *      → syncAccountStatus → redirect z powrotem do /transport/settings
 *        + Filament notification (success/failure)
 *
 * Authorization: tylko FULL_ADMINS tenant'a (owner / admin), bo to
 * decyzja finansowa.
 */
class StripeConnectController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenants,
    ) {}

    // TransporterStripeConnectService NIE jest wstrzykiwany w konstruktorze
    // celowo: jego singleton rzuca, gdy STRIPE_SECRET jest pusty, co
    // wywaliłoby KAŻDĄ z tych tras na 500 zanim zadziała try/catch w akcji.
    // Rozwiązujemy go leniwie w akcjach (wszystkie mają try/catch → przyjazna
    // notyfikacja + redirect zamiast 500).

    public function onboard(Request $request): RedirectResponse
    {
        $tenant = $this->guard($request);

        try {
            $url = app(TransporterStripeConnectService::class)->generateOnboardingLink(
                tenant: $tenant,
                returnUrl: url('/transport/stripe/connect/return'),
                refreshUrl: url('/transport/stripe/connect/onboard'),
            );
        } catch (\Throwable $e) {
            Log::error('Stripe Connect onboarding link failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->danger()
                ->title(__('transport/stripe_connect.notify.onboard_failed'))
                ->body($e->getMessage())
                ->persistent()
                ->send();

            return redirect('/transport/settings');
        }

        return redirect()->away($url);
    }

    public function return(Request $request): RedirectResponse
    {
        $tenant = $this->guard($request);

        try {
            app(TransporterStripeConnectService::class)->syncAccountStatus($tenant);
            $tenant->refresh();
        } catch (\Throwable $e) {
            Log::error('Stripe Connect status sync after return failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->danger()
                ->title(__('transport/stripe_connect.notify.status_sync_failed'))
                ->body($e->getMessage())
                ->send();

            return redirect('/transport/settings');
        }

        $status = (string) $tenant->stripe_connect_status;
        $notification = Notification::make()
            ->title(__("transport/stripe_connect.notify.status_{$status}"));

        if ($status === 'enabled') {
            $notification->success();
        } elseif (in_array($status, ['restricted', 'rejected'], true)) {
            $notification->danger()->persistent();
        } else {
            $notification->warning();
        }

        $notification->send();

        return redirect('/transport/settings');
    }

    /**
     * Manual sync — przycisk „Sprawdź status" w UI gdy transporter widzi
     * stale dane (np. webhook się zgubił).
     */
    public function refresh(Request $request): RedirectResponse
    {
        $tenant = $this->guard($request);

        try {
            app(TransporterStripeConnectService::class)->syncAccountStatus($tenant);
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title(__('transport/stripe_connect.notify.status_sync_failed'))
                ->body($e->getMessage())
                ->send();

            return redirect('/transport/settings');
        }

        Notification::make()
            ->success()
            ->title(__('transport/stripe_connect.notify.refreshed'))
            ->send();

        return redirect('/transport/settings');
    }

    /**
     * Otwiera Stripe Express dashboard w nowej karcie (login link).
     */
    public function dashboard(Request $request): RedirectResponse
    {
        $tenant = $this->guard($request);

        try {
            $url = app(TransporterStripeConnectService::class)->createDashboardLoginLink($tenant);
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title(__('transport/stripe_connect.notify.dashboard_failed'))
                ->body($e->getMessage())
                ->send();

            return redirect('/transport/settings');
        }

        return redirect()->away($url);
    }

    private function guard(Request $request): Tenant
    {
        if (! Auth::check()) {
            abort(401);
        }

        $tenant = $this->tenants->current();
        if ($tenant === null) {
            abort(404, 'No tenant context.');
        }

        if (! $tenant->isTransporter()) {
            abort(403, 'Stripe Connect Express is for transporters only.');
        }

        // FULL_ADMINS = owner/admin — to decyzja finansowa.
        $role = optional($tenant->memberships()
            ->where('user_id', Auth::id())
            ->whereNull('revoked_at')
            ->first())->role;

        if (! in_array((string) $role, TenantRoleGate::FULL_ADMINS, true)) {
            abort(403);
        }

        return $tenant;
    }
}
