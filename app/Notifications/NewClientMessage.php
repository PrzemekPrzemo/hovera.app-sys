<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Apn\ApnMessage;

class NewClientMessage extends Notification
{
    use Queueable;

    public function __construct(public readonly string $clientId, public readonly string $preview) {}

    public function via(object $notifiable): array
    {
        return ['apn', 'fcm'];
    }

    public function toApn(object $notifiable): ApnMessage
    {
        return ApnMessage::create()
            ->title(__('notifications.client_message.title'))
            ->body($this->preview)
            ->custom('kind', 'client_message')
            ->custom('client_id', $this->clientId);
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'notification' => [
                'title' => __('notifications.client_message.title'),
                'body' => $this->preview,
            ],
            'data' => ['kind' => 'client_message', 'client_id' => $this->clientId],
        ];
    }
}
