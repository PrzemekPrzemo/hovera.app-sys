<?php

declare(strict_types=1);

namespace App\Channels;

use Illuminate\Notifications\Notification;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Illuminate\Support\Facades\Log;

/**
 * Custom channel that fans the notification's toFcm() payload out to every
 * device_token (platform=android) the notifiable owns.
 */
class FcmChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toFcm')) {
            return;
        }

        if (! config('push.fcm.enabled')) {
            Log::debug('push.fcm.disabled', ['notification' => $notification::class]);

            return;
        }

        $tokens = method_exists($notifiable, 'routeNotificationFor')
            ? (array) $notifiable->routeNotificationFor('fcm', $notification)
            : [];

        $payload = $notification->toFcm($notifiable);
        $messaging = Firebase::messaging();

        foreach ($tokens as $token) {
            try {
                $messaging->send(CloudMessage::withTarget('token', (string) $token)
                    ->withNotification($payload['notification'] ?? [])
                    ->withData(array_map('strval', (array) ($payload['data'] ?? []))));
            } catch (\Throwable $e) {
                Log::warning('push.fcm.send_failed', ['exception' => $e->getMessage()]);
            }
        }
    }
}
