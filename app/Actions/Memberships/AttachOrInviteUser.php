<?php

declare(strict_types=1);

namespace App\Actions\Memberships;

use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Attach a user to a tenant. Single source of truth used by:
 *   - the Filament memberships UI (admin & tenant panels)
 *   - the CreateTenant action (when supplied with owner_email)
 *   - future invite-by-email flows
 *
 * Behaviour:
 *   - Email already exists → attach (or re-activate revoked) membership
 *   - Email not seen before → create User with a random password and
 *     attach. Caller is responsible for sending an invite e-mail with
 *     a password-reset link (this action returns the generated password
 *     so admin can share it manually if e-mail isn't wired yet).
 */
class AttachOrInviteUser
{
    /**
     * @param  array{tenant_id:string, email:string, role:string, name?:string|null}  $input
     * @return array{membership:TenantMembership, user:User, generated_password:?string}
     */
    public function execute(array $input): array
    {
        $data = $this->validate($input);

        $tenant = Tenant::findOrFail($data['tenant_id']);
        $email = Str::lower($data['email']);

        return DB::connection('central')->transaction(function () use ($tenant, $data, $email) {
            $generatedPassword = null;
            $user = User::where('email', $email)->first();

            if (! $user) {
                $generatedPassword = Str::password(20, symbols: false);
                $user = User::create([
                    'email' => $email,
                    'name' => $data['name'] ?? Str::before($email, '@'),
                    'password' => Hash::make($generatedPassword),
                    'locale' => $tenant->locale,
                    'timezone' => $tenant->timezone,
                ]);
            }

            $membership = TenantMembership::firstOrNew([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
            ]);
            $membership->role = $data['role'];
            $membership->revoked_at = null;
            $membership->joined_at ??= now();
            $membership->save();

            return [
                'membership' => $membership,
                'user' => $user,
                'generated_password' => $generatedPassword,
            ];
        });
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
