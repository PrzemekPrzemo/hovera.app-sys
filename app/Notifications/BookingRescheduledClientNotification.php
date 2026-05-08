<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Confirmation that a self-service reschedule went through. Includes
 * the new time and a portal link so the client can re-verify or undo.
 */
class BookingRescheduledClientNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $tenantName,
        public readonly Carbon $oldStartsAt,
        public readonly Carbon $newStartsAt,
        public readonly int $durationMinutes,
        public readonly string $instructorName,
        public readonly string $cancelUrl,
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
            ->subject(__('notifications.booking_rescheduled.subject', ['tenant' => $this->tenantName]))
            ->greeting(__('notifications.common.greeting'))
            ->line(__('notifications.booking_rescheduled.line_intro', ['tenant' => $this->tenantName]))
            ->line('**'.__('notifications.common.field.old_date').':** '.$this->oldStartsAt->format('Y-m-d H:i'))
            ->line('**'.__('notifications.common.field.new_date').':** '.$this->newStartsAt->format('Y-m-d H:i').' · '.$duration)
            ->line('**'.__('notifications.common.field.instructor').':** '.$this->instructorName)
            ->line(__('notifications.booking_rescheduled.line_undo'))
            ->action(__('notifications.common.cancel_action'), $this->cancelUrl);

        if ($this->portalUrl) {
            $message->line(__('notifications.booking_rescheduled.portal_link', ['url' => $this->portalUrl]));
        }

        return $message->salutation(__('notifications.common.salutation_prefix').$this->tenantName);
    }
}
