<?php

declare(strict_types=1);

namespace App\Domain\Specialists;

use App\Models\Central\ExternalSpecialist;
use App\Models\Central\SpecialistMagicLink;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Notifications\Specialist\SpecialistInvitationNotification;
use App\Services\TenantAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Invite flow dla external specialist (PR O5 Channel B).
 *
 * Stable (lub owner via Channel D) zaprasza vet'a po emailu:
 *   1. Lookup ExternalSpecialist po email — albo create gdy nowy
 *   2. SpecialistMagicLink::issue(KIND_INITIAL_SETUP) — 7d expiry,
 *      issued_for_tenant_id zapisany do audit'u
 *   3. SpecialistInvitationNotification → wysłany na email vet'a
 *      z plain token w URL (jednorazowy show — DB ma tylko hash)
 *   4. Audit log per invite
 *
 * Idempotency: gdy specialist już istnieje i ma `password_hash` set,
 * NIE wysyłamy initial_setup link (już skończył setup) — tylko
 * loguje invite jako "re-issued to existing specialist". Frontend
 * powinien zaproponować separate "re-add to channel" action zamiast
 * resetu setup'u.
 */
class SpecialistInviteService
{
    public function __construct(
        private readonly TenantAuditLogger $audit,
    ) {}

    /**
     * @param  array<string,mixed>  $extra  optional specialty, phone, metadata
     */
    public function invite(
        string $email,
        string $displayName,
        Tenant $invitingTenant,
        User $invitingUser,
        array $extra = [],
    ): SpecialistInviteResult {
        $email = strtolower(trim($email));

        return DB::connection('central')->transaction(function () use (
            $email, $displayName, $invitingTenant, $invitingUser, $extra
        ): SpecialistInviteResult {
            $specialist = ExternalSpecialist::where('email', $email)->first();
            $isNew = $specialist === null;

            if ($isNew) {
                $specialist = ExternalSpecialist::create([
                    'email' => $email,
                    'display_name' => $displayName,
                    'specialty' => $extra['specialty'] ?? null,
                    'phone' => $extra['phone'] ?? null,
                    'metadata' => $extra['metadata'] ?? null,
                    'created_by_user_id' => $invitingUser->id,
                ]);
            }

            // Jeśli już skończył setup — nie regeneruj initial link.
            // Zwracamy ResultStatus::Existing zostawiając caller do
            // decyzji (np. "add to channel" zamiast re-invite).
            if (! $isNew && $specialist->password_hash !== null) {
                $this->audit->record('specialist.invite.reissue_skipped', 'ExternalSpecialist', (string) $specialist->id, [
                    'email' => $email,
                    'inviting_tenant' => $invitingTenant->slug,
                    'inviting_user' => $invitingUser->id,
                    'reason' => 'specialist_already_setup',
                ]);

                return SpecialistInviteResult::existingAlreadySetup($specialist);
            }

            // Inviolate previous unused initial_setup links — będą
            // wycofane via pruneExpired po 7d. Wystawiamy fresh link.
            $link = SpecialistMagicLink::issue(
                specialist: $specialist,
                kind: SpecialistMagicLink::KIND_INITIAL_SETUP,
                tenantId: $invitingTenant->id,
                ipAddress: request()?->ip(),
            );

            Notification::send($specialist, new SpecialistInvitationNotification(
                specialist: $specialist,
                magicLink: $link['model'],
                plainToken: $link['plain_token'],
                invitingTenantName: $invitingTenant->name,
                invitingUserName: $invitingUser->name,
            ));

            $this->audit->record(
                $isNew ? 'specialist.invite.created' : 'specialist.invite.reissued',
                'ExternalSpecialist',
                (string) $specialist->id,
                [
                    'email' => $email,
                    'inviting_tenant' => $invitingTenant->slug,
                    'inviting_user' => $invitingUser->id,
                ],
            );

            return $isNew
                ? SpecialistInviteResult::created($specialist, $link['model'])
                : SpecialistInviteResult::reissued($specialist, $link['model']);
        });
    }
}
