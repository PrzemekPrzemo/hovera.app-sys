<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Sent to the customer right after they submit a public booking
 * request. Confirms receipt and tells them what happens next.
 */
class BookingRequestedClientNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $tenantName,
        public readonly Carbon $startsAt,
        public readonly int $durationMinutes,
        public readonly string $instructorName,
        public readonly string $cancelUrl,
        public readonly int $cancellationPolicyHours,
        public readonly ?string $portalUrl = null,
    ) {}

    /** @return array<int,string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $duration = __('notifications.common.duration_minutes', ['minutes' => $this->durationMinutes]);

        $message = (new MailMessage)
            ->subject(__('notifications.booking_requested.subject', ['tenant' => $this->tenantName]))
            ->greeting(__('notifications.common.greeting'))
            ->line(__('notifications.booking_requested.line_intro', ['tenant' => $this->tenantName]))
            ->line('**'.__('notifications.common.field.term').':** '.$this->startsAt->format('Y-m-d H:i').' · '.$duration)
            ->line('**'.__('notifications.common.field.instructor').':** '.$this->instructorName)
            ->line(__('notifications.booking_requested.line_processing'))
            ->line(__('notifications.common.cancel_policy', ['hours' => $this->cancellationPolicyHours]))
            ->action(__('notifications.common.cancel_action'), $this->cancelUrl)
            ->line(__('notifications.booking_requested.line_pass_warning'));

        if ($this->portalUrl) {
            $message->line(__('notifications.common.portal_link', ['url' => $this->portalUrl]));
        }

        return $message->salutation(__('notifications.common.salutation_prefix').$this->tenantName);
    }
}
