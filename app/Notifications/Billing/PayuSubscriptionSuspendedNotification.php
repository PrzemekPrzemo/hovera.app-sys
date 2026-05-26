<?php

declare(strict_types=1);

namespace App\Notifications\Billing;

use App\Models\Central\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email do owner'a stajni gdy 3. próba cyklicznej opłaty się nie powiedzie
 * → status=cancelled, tenant traci aktywną subskrypcję. Owner musi
 * wykupić ponownie z poziomu /app/billing (nowy setup → nowa karta).
 */
class PayuSubscriptionSuspendedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly ?string $reason = null,
    ) {}

    /** @return array<int,string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $plan = $this->subscription->plan?->name ?? '—';
        $stableName = $this->subscription->tenant?->name ?? '—';

        return (new MailMessage)
            ->subject(__('billing.email.payu_subscription_suspended.subject', [
                'stable' => $stableName,
            ]))
            ->greeting(__('billing.email.payu_subscription_suspended.greeting'))
            ->line(__('billing.email.payu_subscription_suspended.intro', [
                'plan' => $plan,
                'stable' => $stableName,
            ]))
            ->line(__('billing.email.payu_subscription_suspended.consequence'))
            ->action(
                __('billing.email.payu_subscription_suspended.cta'),
                url('/app/billing'),
            )
            ->line(__('billing.email.payu_subscription_suspended.signoff'));
    }
}
