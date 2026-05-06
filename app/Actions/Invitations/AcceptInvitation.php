<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Models\Central\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Validate a plaintext token, set the user's password, mark verified,
 * attach membership (if invitation was tenant-scoped). Idempotent
 * against double-acceptance — a used token is rejected.
 */
class AcceptInvitation
{
    public function lookup(string $plaintextToken): ?UserInvitation
    {
        $hash = UserInvitation::hashToken($plaintextToken);

        return UserInvitation::query()
            ->where('token_hash', $hash)
            ->first();
    }

    /**
     * @return array{user:User, invitation:UserInvitation}
     */
    public function execute(string $plaintextToken, string $password): array
    {
        $invitation = $this->lookup($plaintextToken);

        if (! $invitation) {
            throw new RuntimeException('Zaproszenie nie istnieje.');
        }

        if ($invitation->isAccepted()) {
            throw new RuntimeException('Zaproszenie zostało już wykorzystane.');
        }

        if ($invitation->isExpired()) {
            throw new RuntimeException('Zaproszenie wygasło. Poproś o nowe.');
        }

        if (mb_strlen($password) < 12) {
            throw new RuntimeException('Hasło musi mieć minimum 12 znaków.');
        }

        return DB::connection('central')->transaction(function () use ($invitation, $password) {
            // First-time signup: create the user. Returning user (re-invited
            // after revocation): just update.
            $user = User::firstOrNew(['email' => $invitation->email]);
            $user->name = $user->name ?: ($invitation->name ?: Str::before($invitation->email, '@'));
            $user->password = Hash::make($password);
            $user->locale = $user->locale ?: ($invitation->tenant?->locale ?? 'pl');
            $user->timezone = $user->timezone ?: ($invitation->tenant?->timezone ?? 'Europe/Warsaw');
            $user->email_verified_at ??= now();
            $user->save();

            if ($invitation->tenant_id) {
                $membership = TenantMembership::firstOrNew([
                    'tenant_id' => $invitation->tenant_id,
                    'user_id' => $user->id,
                ]);
                $membership->role = $invitation->role ?: 'viewer';
                $membership->revoked_at = null;
                $membership->joined_at ??= now();
                $membership->save();
            }

            $invitation->forceFill(['accepted_at' => now()])->save();

            return [
                'user' => $user->refresh(),
                'invitation' => $invitation->refresh(),
            ];
        });
    }
}
