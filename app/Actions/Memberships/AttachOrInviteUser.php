<?php

declare(strict_types=1);

namespace App\Actions\Memberships;

use App\Actions\Invitations\SendInvitation;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Models\Central\UserInvitation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Attach a user to a tenant. Single source of truth used by:
 *   - the Filament memberships UI (admin & tenant panels)
 *   - CreateTenant (when supplied with owner_email)
 *   - any future invite flows
 *
 * Behaviour:
 *   - Email already in `users` → attach (or re-activate) membership now,
 *     return mode='attached'
 *   - Email new → create a UserInvitation, send invitation email, do
 *     NOT create the user yet. Membership is created once the user
 *     accepts the invite via /invite/{token}. Returns mode='invited'
 *     and the underlying invitation so callers can show appropriate
 *     UI (e.g. "Invitation sent to ...").
 */
class AttachOrInviteUser
{
    public function __construct(private readonly SendInvitation $sendInvitation) {}

    /**
     * @param  array{tenant_id:string, email:string, role:string, name?:string|null}  $input
     * @return array{
     *     mode:'attached'|'invited',
     *     membership:?TenantMembership,
     *     user:?User,
     *     invitation:?UserInvitation,
     * }
     */
    public function execute(array $input): array
    {
        $data = $this->validate($input);

        $tenant = Tenant::findOrFail($data['tenant_id']);
        $email = Str::lower($data['email']);
        $existing = User::where('email', $email)->first();

        if ($existing) {
            $membership = DB::connection('central')->transaction(function () use ($tenant, $existing, $data) {
                $m = TenantMembership::firstOrNew([
                    'tenant_id' => $tenant->id,
                    'user_id' => $existing->id,
                ]);
                $m->role = $data['role'];
                $m->revoked_at = null;
                $m->joined_at ??= now();
                $m->save();

                return $m;
            });

            return [
                'mode' => 'attached',
                'membership' => $membership,
                'user' => $existing,
                'invitation' => null,
            ];
        }

        $result = $this->sendInvitation->execute(
            email: $email,
            tenant: $tenant,
            role: $data['role'],
            name: $data['name'] ?? null,
            invitedBy: Auth::user(),
        );

        return [
            'mode' => 'invited',
            'membership' => null,
            'user' => null,
            'invitation' => $result['invitation'],
        ];
    }

    private function validate(array $input): array
    {
        $validator = validator($input, [
            'tenant_id' => ['required', 'string', Rule::exists((new Tenant)->getTable(), 'id')],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::in([
                'owner', 'admin', 'manager', 'instructor', 'employee', 'vet', 'viewer',
            ])],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
