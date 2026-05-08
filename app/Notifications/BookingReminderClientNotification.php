<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Sent ~24h before a confirmed booking. Goal is to nudge the client
 * to actually show up (no-show is the #1 pain point for stables) and
 * to surface a cancel link in case they need to back out — better
 * to lose a slot than to host an empty horse.
 */
class BookingReminderClientNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $tenantName,
        public readonly Carbon $startsAt,
        public readonly int $durationMinutes,
        public readonly string $instructorName,
        public readonly ?string $horseName,
        public readonly ?string $arenaName,
        public readonly ?string $stableAddress,
        public readonly ?string $stablePhone,
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
            ->subject(__('notifications.booking_reminder.subject', [
                'time' => $this->startsAt->format('H:i'),
                'tenant' => $this->tenantName,
            ]))
            ->greeting(__('notifications.common.greeting'))
            ->line(__('notifications.booking_reminder.line_intro'))
            ->line('**'.__('notifications.common.field.term').':** '.$this->startsAt->format('Y-m-d H:i').' · '.$duration)
            ->line('**'.__('notifications.common.field.instructor').':** '.$this->instructorName);

        if ($this->horseName) {
            $message->line('**'.__('notifications.common.field.horse').':** '.$this->horseName);
        }
        if ($this->arenaName) {
            $message->line('**'.__('notifications.common.field.arena').':** '.$this->arenaName);
        }
        if ($this->stableAddress) {
            $message->line('**'.__('notifications.common.field.address').':** '.$this->stableAddress);
        }
        if ($this->stablePhone) {
            $message->line('**'.__('notifications.common.field.phone').':** '.$this->stablePhone);
        }

        $message
            ->line(__('notifications.booking_reminder.cancel_policy', ['hours' => $this->cancellationPolicyHours]))
            ->action(__('notifications.common.cancel_action'), $this->cancelUrl);

        if ($this->portalUrl) {
            $message->line(__('notifications.common.portal_link', ['url' => $this->portalUrl]));
        }

        return $message
            ->line(__('notifications.booking_reminder.line_signoff'))
            ->salutation(__('notifications.common.salutation_prefix').$this->tenantName);
    }
}
