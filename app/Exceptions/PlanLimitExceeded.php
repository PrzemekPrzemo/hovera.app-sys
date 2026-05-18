<?php

declare(strict_types=1);

namespace App\Exceptions;

use Filament\Notifications\Notification;
use RuntimeException;
use Throwable;

/**
 * Rzucany gdy próba dodania zasobu (koń / klient / user) przekracza
 * `tenant->effectiveLimits()`. Filament resource catch'uje ten wyjątek
 * w `mutateFormDataBeforeCreate`/`beforeCreate` i pokazuje notyfikację —
 * wtedy `render()` zwraca friendly message zamiast 500.
 */
class PlanLimitExceeded extends RuntimeException
{
    public function __construct(
        string $message = '',
        public readonly string $resource = '',
        public readonly int $limit = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function horses(int $limit): self
    {
        return new self(
            message: __('billing.limits.horses_exceeded', ['limit' => $limit]),
            resource: 'horse',
            limit: $limit,
        );
    }

    public static function clients(int $limit): self
    {
        return new self(
            message: __('billing.limits.clients_exceeded', ['limit' => $limit]),
            resource: 'client',
            limit: $limit,
        );
    }

    public static function vehicles(int $limit): self
    {
        return new self(
            message: __('billing.limits.vehicles_exceeded', ['limit' => $limit]),
            resource: 'vehicle',
            limit: $limit,
        );
    }

    public static function drivers(int $limit): self
    {
        return new self(
            message: __('billing.limits.drivers_exceeded', ['limit' => $limit]),
            resource: 'driver',
            limit: $limit,
        );
    }

    /**
     * Filament hook — gdy wyjątek poleci z resource action, pokaż
     * notification i abortuj akcję halt'em (Filament konwencja).
     */
    public function notify(): void
    {
        Notification::make()
            ->danger()
            ->title(__('billing.limits.title'))
            ->body($this->getMessage())
            ->persistent()
            ->send();
    }
}
