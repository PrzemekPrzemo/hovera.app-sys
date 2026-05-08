<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Supported payment providers per stajnia. Each tenant picks a default
 * in their settings; later iterations can support per-payment override.
 *
 * `Stub` is dev/test only — never exposed in stable settings UI. It
 * returns a fake hosted-checkout URL the test suite can drive
 * deterministically.
 */
enum PaymentProvider: string
{
    case None = 'none';
    case Stub = 'stub';
    case P24 = 'p24';
    case PayU = 'payu';
    case Stripe = 'stripe';
    case Mollie = 'mollie';

    public function label(): string
    {
        return __('enums.payment_provider.'.$this->value);
    }

    /**
     * Subset shown in tenant settings UI — Stub is only for tests.
     *
     * @return array<string,string>
     */
    public static function tenantOptions(): array
    {
        return collect(self::cases())
            ->filter(fn (self $p) => $p !== self::Stub)
            ->mapWithKeys(fn (self $p) => [$p->value => $p->label()])
            ->all();
    }

    public function requiresCredentials(): bool
    {
        return ! in_array($this, [self::None, self::Stub], true);
    }
}
