<?php

declare(strict_types=1);

namespace App\Notifications\Boarding;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notyfikuje stajnię że właściciel odrzucił boarding request z podanym
 * powodem. Dispatch z `PendingBoardingRequestResource::handleReject` (PR 2).
 *
 * Status assignment'u idzie na `disputed`. Stable może spróbować
 * ponownie po skontaktowaniu się z owner'em (PR 6+).
 */
class HorseBoardingRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $assignmentId,
        public readonly string $ownerName,
        public readonly string $ownerEmail,
        public readonly string $centralHorseId,
        public readonly string $horseName,
        public readonly string $reason,
    ) {}

    /** @return array<int,string> */
    public function via(mixed $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array<string,mixed> */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'kind' => 'boarding.rejected',
            'assignment_id' => $this->assignmentId,
            'owner_name' => $this->ownerName,
            'owner_email' => $this->ownerEmail,
            'central_horse_id' => $this->centralHorseId,
            'horse_name' => $this->horseName,
            'reason' => $this->reason,
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.boarding_rejected.subject', [
                'horse' => $this->horseName,
            ]))
            ->greeting(__('notifications.common.greeting'))
            ->line(__('notifications.boarding_rejected.line_intro', [
                'owner' => $this->ownerName,
                'horse' => $this->horseName,
            ]))
            ->line('> '.$this->reason)
            ->line(__('notifications.boarding_rejected.line_contact', [
                'email' => $this->ownerEmail,
            ]));
    }
}
