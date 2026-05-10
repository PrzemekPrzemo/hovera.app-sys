<?php

declare(strict_types=1);

namespace App\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use NotificationChannels\Apn\ApnAdapter;
use NotificationChannels\Apn\ApnChannel as PackageApnChannel;
use Pushok\AuthProvider\Token;
use Pushok\Client;
use Pushok\Notification as ApnsNotification;

/**
 * Thin wrapper around the package channel that:
 *   - reads token-based credentials from config/push.php,
 *   - sends to every iOS device_token the notifiable owns,
 *   - logs and swallows transient APNs errors so a single bad device
 *     never breaks the queue worker.
 */
class ApnChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toApn')) {
            return;
        }

        if (! config('push.apn.enabled')) {
            Log::debug('push.apn.disabled', ['notification' => $notification::class]);

            return;
        }

        $tokens = method_exists($notifiable, 'routeNotificationFor')
            ? (array) $notifiable->routeNotificationFor('apn', $notification)
            : [];

        if ($tokens === []) {
            return;
        }

        $authProvider = Token::create([
            'key_id' => (string) config('push.apn.key_id'),
            'team_id' => (string) config('push.apn.team_id'),
            'app_bundle_id' => (string) config('push.apn.app_bundle_id'),
            'private_key_content' => (string) config('push.apn.private_key'),
        ]);

        $client = new Client($authProvider, (bool) config('push.apn.production', false));

        $message = $notification->toApn($notifiable);
        $payload = (new ApnAdapter)->adapt($message);

        foreach ($tokens as $token) {
            try {
                $apns = new ApnsNotification($payload, (string) $token);
                $apns->setPriority(10);
                $apns->setApnsTopic((string) config('push.apn.app_bundle_id'));
                $client->addNotification($apns);
            } catch (\Throwable $e) {
                Log::warning('push.apn.queue_failed', ['exception' => $e->getMessage()]);
            }
        }

        try {
            $client->push();
        } catch (\Throwable $e) {
            Log::warning('push.apn.push_failed', ['exception' => $e->getMessage()]);
        }
    }
}
