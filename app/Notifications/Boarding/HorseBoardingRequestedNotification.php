<?php

declare(strict_types=1);

namespace App\Notifications\Boarding;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notyfikuje właściciela konia że stajnia wysłała request o boarding
 * jego konia. Owner powinien wejść do /owner/pending-boarding-requests
 * (PR 2) i zaakceptować lub odrzucić.
 *
 * Dispatch z `HorseResource::Pages\ListHorses::importFromRegistryAction`
 * (PR 1) po pomyślnym `requestBoarding()`.
 *
 * Channels: database (Owner Dashboard widget powiadomień), mail.
 */
class HorseBoardingRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $assignmentId,
        public readonly string $stableTenantId,
        public readonly string $stableName,
        public readonly string $centralHorseId,
        public readonly string $horseName,
        public readonly string $ownerPanelUrl,
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
            'kind' => 'boarding.requested',
            'assignment_id' => $this->assignmentId,
            'stable_tenant_id' => $this->stableTenantId,
            'stable_name' => $this->stableName,
            'central_horse_id' => $this->centralHorseId,
            'horse_name' => $this->horseName,
            'url' => $this->ownerPanelUrl,
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.boarding_requested.subject', [
                'horse' => $this->horseName,
                'stable' => $this->stableName,
            ]))
            ->greeting(__('notifications.common.greeting'))
            ->line(__('notifications.boarding_requested.line_intro', [
                'stable' => $this->stableName,
                'horse' => $this->horseName,
            ]))
            ->line(__('notifications.boarding_requested.line_action'))
            ->action(__('notifications.boarding_requested.action'), $this->ownerPanelUrl);
    }
}
