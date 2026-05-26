<?php

declare(strict_types=1);

namespace App\Notifications\Billing;

use App\Models\Central\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email do owner'a stajni gdy PayU odrzuci cykliczną opłatę. Wysyłany
 * przy każdej nieudanej próbie (1: retry +3d, 2: retry +7d). Trzeci fail
 * triggeruje PayuSubscriptionSuspendedNotification (osobny email).
 */
class PayuChargeFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly int $attempts,
        public readonly ?string $reason = null,
    ) {}

    /** @return array<int,string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $nextRetryDays = $this->attempts === 1 ? 3 : 7;
        $plan = $this->subscription->plan?->name ?? '—';
        $stableName = $this->subscription->tenant?->name ?? '—';

        return (new MailMessage)
            ->subject(__('billing.email.payu_charge_failed.subject', [
                'stable' => $stableName,
            ]))
            ->greeting(__('billing.email.payu_charge_failed.greeting'))
            ->line(__('billing.email.payu_charge_failed.intro', [
                'plan' => $plan,
                'stable' => $stableName,
            ]))
            ->line(__('billing.email.payu_charge_failed.attempt', [
                'attempts' => $this->attempts,
                'next_in_days' => $nextRetryDays,
            ]))
            ->line(__('billing.email.payu_charge_failed.fix'))
            ->action(
                __('billing.email.payu_charge_failed.cta'),
                url('/app/billing'),
            )
            ->line(__('billing.email.payu_charge_failed.signoff'));
    }
}
