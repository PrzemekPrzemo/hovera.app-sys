<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant\HorseMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Apn\ApnMessage;

class NewHorseMessage extends Notification
{
    use Queueable;

    public function __construct(public readonly HorseMessage $message) {}

    public function via(object $notifiable): array
    {
        return ['apn', 'fcm'];
    }

    public function toApn(object $notifiable): ApnMessage
    {
        return ApnMessage::create()
            ->title(__('notifications.horse_message.title'))
            ->body($this->preview())
            ->custom('kind', 'horse_message')
            ->custom('horse_id', (string) $this->message->horse_id)
            ->custom('message_id', (string) $this->message->id);
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'notification' => [
                'title' => __('notifications.horse_message.title'),
                'body' => $this->preview(),
            ],
            'data' => [
                'kind' => 'horse_message',
                'horse_id' => (string) $this->message->horse_id,
                'message_id' => (string) $this->message->id,
            ],
        ];
    }

    private function preview(): string
    {
        return mb_substr((string) ($this->message->body ?? ''), 0, 140);
    }
}
