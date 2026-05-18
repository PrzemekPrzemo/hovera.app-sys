<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Actions\Invitations\SendInvitation;
use App\Enums\TenantType;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Tenancy\Provisioner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Atomically: create tenant row, provision its DB, optionally attach
 * an owner. If provisioning fails the central row is rolled back and
 * any partially created MySQL artifacts are cleaned up.
 */
class CreateTenant
{
    public function __construct(private readonly Provisioner $provisioner) {}

    /**
     * @param  array{
     *     slug:string, name:string,
     *     type?:string,
     *     country?:string, locale?:string, timezone?:string, currency?:string,
     *     plan_code?:string|null,
     *     owner_email?:string|null, owner_name?:string|null,
     * }  $input
     */
    public function execute(array $input): Tenant
    {
        $data = $this->validate($input);
        $type = TenantType::from($data['type']);
        [$dbName, $dbUser] = array_values($this->provisioner->makeIdentifiers($data['slug']));
        $dbPassword = $this->provisioner->generatePassword();

        // Trial 2.0 — domyślny plan zależy od typu tenanta:
        //   stable      → pro (pełen feature set), trial cap'i konie/klientów
        //   transporter → transport_pro (5 pojazdów, 10 kierowców)
        // Caller może override przez plan_code.
        $defaultPlanCode = match ($type) {
            TenantType::Stable => 'pro',
            TenantType::Transporter => 'transport_pro',
        };
        $plan = ! empty($data['plan_code'])
            ? Plan::where('code', $data['plan_code'])->first()
            : Plan::where('code', $defaultPlanCode)->first();

        $tenant = DB::connection('central')->transaction(function () use ($data, $type, $dbName, $dbUser, $dbPassword, $plan) {
            $tenant = new Tenant([
                'slug' => $data['slug'],
                'name' => $data['name'],
                'type' => $type,
                // Transporter startuje z verification_status=pending — musi wgrać
                // dokumenty zanim master admin go zweryfikuje. Stable tenant
                // dostaje NULL (irrelevant).
                'verification_status' => $type === TenantType::Transporter
                    ? \App\Enums\VerificationStatus::Pending
                    : null,
                'country' => $data['country'],
                'locale' => $data['locale'],
                'timezone' => $data['timezone'],
                'currency' => $data['currency'],
                'plan_id' => $plan?->id,
                'status' => 'provisioning',
                'db_host' => config('hovera.tenant.db_host'),
                'db_port' => config('hovera.tenant.db_port'),
                'db_name' => $dbName,
                'db_username' => $dbUser,
            ]);
            $tenant->db_password = $dbPassword;        // mutator → encrypted
            $tenant->save();

            return $tenant;
        });

        try {
            $this->provisioner->provision($tenant);
        } catch (Throwable $e) {
            Log::error('Tenant provisioning failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
            $this->rollback($tenant);
            throw $e;
        }

        // Trial caps: tylko dla stajni (trial_max_horses / trial_max_clients).
        // Transporter dziedziczy limity wprost z planu (max_vehicles, max_drivers).
        $postProvisionAttrs = [
            'status' => 'trialing',
            'trial_ends_at' => $tenant->trial_ends_at ?? now()->addDays(30),
        ];
        if ($type === TenantType::Stable) {
            $postProvisionAttrs['trial_max_horses'] = $tenant->trial_max_horses ?? 10;
            $postProvisionAttrs['trial_max_clients'] = $tenant->trial_max_clients ?? 5;
        }
        $tenant->forceFill($postProvisionAttrs)->save();

        $ownerEmail = $data['owner_email'] ?? null;
        if (! empty($ownerEmail)) {
            $this->attachOwner($tenant, $ownerEmail, $data['owner_name'] ?? null);
        }

        return $tenant->fresh();
    }

    private function rollback(Tenant $tenant): void
    {
        try {
            $this->provisioner->destroy($tenant);
        } catch (Throwable $e) {
            Log::error('Provisioning rollback failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
        }
        $tenant->forceDelete();
    }

    private function attachOwner(Tenant $tenant, string $email, ?string $name): void
    {
        $email = Str::lower($email);
        $existing = User::where('email', $email)->first();

        // Existing user — attach immediately as owner.
        if ($existing) {
            TenantMembership::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'user_id' => $existing->id,
                ],
                [
                    'role' => 'owner',
                    'joined_at' => now(),
                ],
            );

            return;
        }

        // New user — send invitation. Membership is created on accept.
        app(SendInvitation::class)->execute(
            email: $email,
            tenant: $tenant,
            role: 'owner',
            name: $name,
        );
    }

    private function validate(array $input): array
    {
        $validator = validator($input, [
            'slug' => ['required', 'string', 'min:3', 'max:63',
                'regex:/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])?$/',
                Rule::unique(Tenant::class, 'slug')],
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'type' => ['sometimes', 'string', Rule::in(['stable', 'transporter'])],
            'country' => ['sometimes', 'string', 'size:2'],
            'locale' => ['sometimes', 'string', 'max:10'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'plan_code' => ['sometimes', 'nullable', 'string', 'max:32'],
            'owner_email' => ['sometimes', 'nullable', 'email'],
            'owner_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return array_replace([
            'type' => 'stable',
            'country' => 'PL',
            'locale' => 'pl',
            'timezone' => 'Europe/Warsaw',
            'currency' => 'PLN',
        ], $validator->validated());
    }
}
