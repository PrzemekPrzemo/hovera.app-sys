<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Owner;

use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\User;
use App\Models\Tenant\Client;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Helper dla dispatch'u notifications do ownerów koni. Stable side
 * tworzy notification (np. NewMessageForOwner) i woła dispatcher
 * z Client albo central_horse_id — my znajdziemy User w central DB
 * i `$user->notify($notification)`.
 *
 * Wszystkie metody są soft-fail (log + report, nie crash'ują głównej
 * akcji jak wystawienie faktury czy dodanie health record).
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 6 PR 6.1".
 */
class OwnerNotificationDispatcher
{
    /**
     * Dispatch do ownera resolveowanego z Client.central_user_id (gdy
     * jesteśmy w stable context z Client modelem).
     */
    public function forClient(?Client $client, Notification $notification): void
    {
        if ($client === null || $client->central_user_id === null) {
            return; // legacy client bez linku do central
        }

        $this->dispatchToUserId((string) $client->central_user_id, $notification);
    }

    /**
     * Dispatch do ownera resolveowanego z central_horse_id (gdy mamy
     * tylko ID konia np. z health record). Szuka primary_owner w
     * CentralHorseRegistry.
     */
    public function forCentralHorse(?string $centralHorseId, Notification $notification): void
    {
        if ($centralHorseId === null || $centralHorseId === '') {
            return;
        }

        $registry = CentralHorseRegistry::query()->find($centralHorseId);
        if ($registry === null || $registry->primary_owner_user_id === null) {
            return;
        }

        $this->dispatchToUserId((string) $registry->primary_owner_user_id, $notification);
    }

    /**
     * Bezpośredni dispatch po user ID. Soft-fail.
     */
    private function dispatchToUserId(string $userId, Notification $notification): void
    {
        try {
            $user = User::query()->find($userId);
            if ($user === null) {
                return;
            }
            $user->notify($notification);
        } catch (Throwable $e) {
            // Notification dispatch padł (np. SMTP error przy mail channel).
            // Logujemy ale głównej akcji nie cofamy — wiadomość/faktura/
            // health record już zapisane.
            Log::warning('Owner notification dispatch failed', [
                'user_id' => $userId,
                'notification' => $notification::class,
                'error' => $e->getMessage(),
            ]);
            report($e);
        }
    }
}
