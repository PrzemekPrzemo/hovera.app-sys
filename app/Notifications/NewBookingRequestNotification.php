<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class NewBookingRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $tenantName,
        public readonly string $tenantSlug,
        public readonly string $entryId,
        public readonly Carbon $startsAt,
        public readonly string $instructorName,
        public readonly string $clientName,
        public readonly string $clientEmail,
        public readonly ?string $clientPhone,
        public readonly ?string $notes,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $url = url('/app/calendar-entries/'.$this->entryId.'/edit');

        $clientFormatted = $this->clientPhone
            ? __('notifications.new_booking_request.client_format_with_phone', [
                'name' => $this->clientName,
                'email' => $this->clientEmail,
                'phone' => $this->clientPhone,
            ])
            : __('notifications.new_booking_request.client_format', [
                'name' => $this->clientName,
                'email' => $this->clientEmail,
            ]);

        $message = (new MailMessage)
            ->subject(__('notifications.new_booking_request.subject', ['tenant' => $this->tenantName]))
            ->greeting(__('notifications.common.greeting'))
            ->line(__('notifications.new_booking_request.line_intro', ['tenant' => $this->tenantName]))
            ->line('**'.__('notifications.common.field.term').':** '.$this->startsAt->format('Y-m-d H:i'))
            ->line('**'.__('notifications.common.field.instructor').':** '.$this->instructorName)
            ->line('**'.__('notifications.common.field.client').':** '.$clientFormatted);

        if ($this->notes) {
            $message->line('**'.__('notifications.common.field.client_note').':** '.$this->notes);
        }

        return $message
            ->line(__('notifications.new_booking_request.line_action_required'))
            ->action(__('notifications.new_booking_request.action'), $url)
            ->line(__('notifications.new_booking_request.line_horse_assignment'))
            ->salutation(__('notifications.new_booking_request.salutation'));
    }
}
