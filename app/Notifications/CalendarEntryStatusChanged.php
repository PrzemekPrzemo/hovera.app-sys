<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant\CalendarEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Apn\ApnChannel;
use NotificationChannels\Apn\ApnMessage;
use Kreait\Laravel\Firebase\Facades\Firebase;

class CalendarEntryStatusChanged extends Notification
{
    use Queueable;

    public function __construct(public readonly CalendarEntry $entry) {}

    public function via(object $notifiable): array
    {
        return ['apn', 'fcm'];
    }

    public function toApn(object $notifiable): ApnMessage
    {
        return ApnMessage::create()
            ->title(__('notifications.calendar.title'))
            ->body(__('notifications.calendar.status', ['status' => $this->entry->status?->value ?? '']))
            ->custom('entry_id', (string) $this->entry->id)
            ->custom('kind', 'calendar_entry');
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'notification' => [
                'title' => __('notifications.calendar.title'),
                'body' => __('notifications.calendar.status', ['status' => $this->entry->status?->value ?? '']),
            ],
            'data' => [
                'kind' => 'calendar_entry',
                'entry_id' => (string) $this->entry->id,
            ],
        ];
    }
}
