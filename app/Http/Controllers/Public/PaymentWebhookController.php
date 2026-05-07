<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\PaymentProvider as PaymentProviderEnum;
use App\Models\Central\Tenant;
use App\Models\Tenant\Payment;
use App\Services\Payments\PaymentProviderRegistry;
use App\Tenancy\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Universal webhook + return endpoints. The provider id is the URL
 * segment; we resolve the provider and let it handle the rest.
 *
 * Webhook routes are tenant-scoped via slug in URL — providers can't
 * deliver to a "wrong" tenant because their own settings define the
 * URL they call.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenants,
        private readonly PaymentProviderRegistry $registry,
    ) {}

    public function webhook(Request $request, string $slug, string $provider): Response
    {
        $tenant = $this->resolveAndActivate($slug);
        $providerEnum = PaymentProviderEnum::tryFrom($provider);
        if (! $providerEnum) {
            abort(404);
        }

        return $this->registry->for($providerEnum)->handleWebhook($request, $tenant);
    }

    public function return(Request $request, string $slug, string $provider, string $paymentId): Response
    {
        $tenant = $this->resolveAndActivate($slug);
        $providerEnum = PaymentProviderEnum::tryFrom($provider);
        if (! $providerEnum) {
            abort(404);
        }

        $payment = Payment::query()->find($paymentId);
        if (! $payment) {
            abort(404);
        }

        return $this->registry->for($providerEnum)->handleReturn($request, $tenant, $payment);
    }

    private function resolveAndActivate(string $slug): Tenant
    {
        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $slug)) {
            abort(404);
        }

        $tenant = Cache::remember(
            "public_booking_tenant:{$slug}",
            now()->addMinute(),
            fn () => Tenant::query()
                ->where('slug', $slug)
                ->whereIn('status', ['trialing', 'active', 'past_due'])
                ->first(),
        );

        if (! $tenant) {
            abort(404);
        }

        if ($this->tenants->current()?->id !== $tenant->id) {
            $this->tenants->setCurrent($tenant);
        }

        return $tenant;
    }
}
