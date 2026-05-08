<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a tenant payment.
 *
 *   pending      → created in our DB, no checkout session yet
 *   processing   → user clicked "pay", we have a provider session id;
 *                  awaiting webhook
 *   succeeded    → provider confirmed payment (final, terminal)
 *   failed       → provider returned an error or user gave up
 *   refunded     → reverse charge requested + confirmed (terminal)
 *
 * `expired` is intentionally absent — when a hosted-checkout session
 * times out we set status=failed with metadata.reason='expired'. One
 * less terminal state to enumerate downstream.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return __('enums.payment_status.'.$this->value);
    }

    public function isTerminal(): bool
    {
        return $this === self::Succeeded
            || $this === self::Failed
            || $this === self::Refunded;
    }

    /**
     * @return array<string,string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $s) => [$s->value => $s->label()])
            ->all();
    }
}
