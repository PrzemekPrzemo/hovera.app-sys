<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant\StableActivity;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Apn\ApnMessage;

class StableActivityAssigned extends Notification
{
    use Queueable;

    public function __construct(public readonly StableActivity $activity) {}

    public function via(object $notifiable): array
    {
        return ['apn', 'fcm'];
    }

    public function toApn(object $notifiable): ApnMessage
    {
        return ApnMessage::create()
            ->title(__('notifications.stable_activity.title'))
            ->body((string) ($this->activity->title ?? $this->activity->kind ?? ''))
            ->custom('kind', 'stable_activity')
            ->custom('activity_id', (string) $this->activity->id);
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'notification' => [
                'title' => __('notifications.stable_activity.title'),
                'body' => (string) ($this->activity->title ?? $this->activity->kind ?? ''),
            ],
            'data' => ['kind' => 'stable_activity', 'activity_id' => (string) $this->activity->id],
        ];
    }
}
