<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Central\UserInvitation;
use App\Notifications\UserInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;

/**
 * Create an invitation row, send the email, return the invitation
 * (caller can decide whether to surface the plaintext token e.g.
 * for tests). Existing pending invitations for the same
 * (email, tenant_id) are revoked first to avoid race conditions.
 *
 * @return array{invitation:UserInvitation, plaintext_token:string}
 */
class SendInvitation
{
    public function execute(
        string $email,
        ?Tenant $tenant = null,
        ?string $role = null,
        ?string $name = null,
        ?User $invitedBy = null,
        int $ttlDays = 7,
    ): array {
        $email = Str::lower($email);
        $plaintextToken = UserInvitation::generateToken();
        $hash = UserInvitation::hashToken($plaintextToken);

        $invitation = DB::connection('central')->transaction(function () use (
            $email, $tenant, $role, $name, $invitedBy, $ttlDays, $hash
        ) {
            // Revoke any prior pending invites — only the latest matters.
            UserInvitation::query()
                ->where('email', $email)
                ->when($tenant, fn ($q) => $q->where('tenant_id', $tenant->id))
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->update(['expires_at' => now()->subSecond()]);

            return UserInvitation::create([
                'email' => $email,
                'tenant_id' => $tenant?->id,
                'role' => $role,
                'name' => $name,
                'token_hash' => $hash,
                'invited_by_user_id' => $invitedBy?->id,
                'expires_at' => now()->addDays($ttlDays),
            ]);
        });

        NotificationFacade::route('mail', $email)
            ->notify(new UserInvitationNotification($invitation, $plaintextToken));

        return [
            'invitation' => $invitation,
            'plaintext_token' => $plaintextToken,
        ];
    }
}
