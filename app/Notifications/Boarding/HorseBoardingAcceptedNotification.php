<?php

declare(strict_types=1);

namespace App\Notifications\Boarding;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notyfikuje stajnię (team members z `owner/admin/manager` rolami) że
 * właściciel zaakceptował boarding request. Stable od tego momentu
 * widzi konia w /app/horses i może go przypisać do boksu (PR 6).
 *
 * Dispatch z `PendingBoardingRequestResource::handleAccept` (PR 2) na
 * wszystkich team members tenant'a (z central User).
 */
class HorseBoardingAcceptedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $assignmentId,
        public readonly string $ownerName,
        public readonly string $ownerEmail,
        public readonly string $centralHorseId,
        public readonly string $horseName,
        public readonly string $stableHorseUrl,
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
            'kind' => 'boarding.accepted',
            'assignment_id' => $this->assignmentId,
            'owner_name' => $this->ownerName,
            'owner_email' => $this->ownerEmail,
            'central_horse_id' => $this->centralHorseId,
            'horse_name' => $this->horseName,
            'url' => $this->stableHorseUrl,
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.boarding_accepted.subject', [
                'horse' => $this->horseName,
            ]))
            ->greeting(__('notifications.common.greeting'))
            ->line(__('notifications.boarding_accepted.line_intro', [
                'owner' => $this->ownerName,
                'horse' => $this->horseName,
            ]))
            ->line(__('notifications.boarding_accepted.line_next_step'))
            ->action(__('notifications.boarding_accepted.action'), $this->stableHorseUrl);
    }
}
