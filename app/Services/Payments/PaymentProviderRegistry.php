<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\PaymentProvider;
use App\Models\Central\Tenant;
use App\Services\Payments\Contracts\PaymentProviderInterface;
use App\Services\Payments\Providers\MolliePaymentProvider;
use App\Services\Payments\Providers\P24PaymentProvider;
use App\Services\Payments\Providers\PayUPaymentProvider;
use App\Services\Payments\Providers\StripePaymentProvider;
use App\Services\Payments\Providers\StubPaymentProvider;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves a payment provider by its enum value. Singleton — providers
 * themselves are stateless; we just keep one instance per process.
 *
 * Tests can replace the registry binding to inject mocked providers.
 */
class PaymentProviderRegistry
{
    public function __construct(private readonly Container $container) {}

    public function for(PaymentProvider $provider): PaymentProviderInterface
    {
        return match ($provider) {
            PaymentProvider::Stub => $this->container->make(StubPaymentProvider::class),
            PaymentProvider::P24 => $this->container->make(P24PaymentProvider::class),
            PaymentProvider::PayU => $this->container->make(PayUPaymentProvider::class),
            PaymentProvider::Stripe => $this->container->make(StripePaymentProvider::class),
            PaymentProvider::Mollie => $this->container->make(MolliePaymentProvider::class),
            PaymentProvider::None => throw PaymentProviderException::notConfigured('none', 'provider'),
        };
    }

    /**
     * Resolve the default provider configured for this tenant.
     * Used when initiating a payment without an explicit override.
     */
    public function defaultFor(Tenant $tenant): PaymentProviderInterface
    {
        $code = (string) (data_get($tenant->settings, 'payments.default_provider') ?? 'none');
        $provider = PaymentProvider::tryFrom($code) ?? PaymentProvider::None;

        return $this->for($provider);
    }
}
