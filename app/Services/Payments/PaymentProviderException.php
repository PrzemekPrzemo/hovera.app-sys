<?php

declare(strict_types=1);

namespace App\Services\Payments;

class PaymentProviderException extends \RuntimeException
{
    public static function notConfigured(string $provider, string $missing): self
    {
        return new self("Provider {$provider} nie jest skonfigurowany — brakuje: {$missing}.");
    }

    public static function notImplemented(string $provider, string $method): self
    {
        return new self("Provider {$provider} nie obsługuje akcji: {$method}.");
    }

    public static function apiError(string $provider, string $detail): self
    {
        return new self("Błąd integracji {$provider}: {$detail}");
    }
}
