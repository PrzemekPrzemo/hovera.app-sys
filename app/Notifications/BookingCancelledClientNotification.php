<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Sent to the customer when their booking is cancelled — either by
 * the stable owner from /app, or by themselves through the cancel
 * link.
 */
class BookingCancelledClientNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $tenantName,
        public readonly Carbon $startsAt,
        public readonly string $instructorName,
        public readonly string $cancelledBy,   // 'stable' | 'client'
        public readonly bool $passRestored,
    ) {}

    /** @return array<int,string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('notifications.booking_cancelled.subject', ['tenant' => $this->tenantName]))
            ->greeting(__('notifications.common.greeting'));

        if ($this->cancelledBy === 'client') {
            $message->line(__('notifications.booking_cancelled.line_by_client', ['tenant' => $this->tenantName]));
        } else {
            $message->line(__('notifications.booking_cancelled.line_by_stable', ['tenant' => $this->tenantName]));
        }

        $message->line('**'.__('notifications.common.field.cancelled_term').':** '.$this->startsAt->format('Y-m-d H:i'))
            ->line('**'.__('notifications.common.field.instructor').':** '.$this->instructorName);

        $message->line($this->passRestored
            ? __('notifications.booking_cancelled.pass_restored')
            : __('notifications.booking_cancelled.pass_not_restored'));

        return $message->salutation(__('notifications.common.salutation_prefix').$this->tenantName);
    }
}
