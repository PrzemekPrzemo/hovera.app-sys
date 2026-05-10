<?php

declare(strict_types=1);

namespace App\Services\Push;

use App\Models\Central\DeviceToken;
use App\Models\Central\User;

class DeviceTokenService
{
    public function register(User $user, array $payload): DeviceToken
    {
        return DeviceToken::query()->updateOrCreate(
            ['token' => (string) $payload['token']],
            [
                'user_id' => $user->id,
                'platform' => (string) $payload['platform'],
                'locale' => (string) ($payload['locale'] ?? $user->locale ?? 'pl'),
                'app_version' => $payload['app_version'] ?? null,
                'device_model' => $payload['device_model'] ?? null,
                'last_seen_at' => now(),
            ]
        );
    }

    public function unregister(User $user, string $token): void
    {
        DeviceToken::query()
            ->forUser($user)
            ->where('token', $token)
            ->delete();
    }
}
