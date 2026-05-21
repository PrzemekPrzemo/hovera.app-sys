<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Central\UserInvitation;
use App\Notifications\UserInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;
use Throwable;

/**
 * Create an invitation row, send the email, return the invitation
 * (caller can decide whether to surface the plaintext token e.g.
 * for tests). Existing pending invitations for the same
 * (email, tenant_id) are revoked first to avoid race conditions.
 *
 * Mail dispatch jest soft-fail: jeśli SMTP padnie (timeout / config /
 * provider down), logujemy + report'ujemy ale NIE rzucamy dalej.
 * Powód: invitation row już jest w DB (transakcja committed) — caller
 * (np. HorseOwnerRegistrationController) musi móc kontynuować i pokazać
 * "sprawdź email" thanks page, a admin może później wysłać invitację
 * ponownie z panelu. Wcześniejszy hard-fail powodował że błąd SMTP
 * crashował cały signup flow z bezsensownym "provisioning_failed".
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

        try {
            NotificationFacade::route('mail', $email)
                ->notify(new UserInvitationNotification($invitation, $plaintextToken));
        } catch (Throwable $e) {
            // SMTP / mail driver fail — invitation row jest w DB, admin
            // może resend ręcznie. NIE rzucamy żeby tenant provisioning
            // flow zakończył się sukcesem i user zobaczył thanks page.
            report($e);
            Log::warning('Invitation email dispatch failed (soft-fail)', [
                'email' => $email,
                'tenant_id' => $tenant?->id,
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'invitation' => $invitation,
            'plaintext_token' => $plaintextToken,
        ];
    }
}
