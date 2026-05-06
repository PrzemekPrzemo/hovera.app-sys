<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Tenancy\Provisioner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
     *     country?:string, locale?:string, timezone?:string, currency?:string,
     *     plan_code?:string|null,
     *     owner_email?:string|null, owner_name?:string|null,
     * }  $input
     */
    public function execute(array $input): Tenant
    {
        $data = $this->validate($input);
        [$dbName, $dbUser] = array_values($this->provisioner->makeIdentifiers($data['slug']));
        $dbPassword = $this->provisioner->generatePassword();

        $plan = ! empty($data['plan_code'])
            ? Plan::where('code', $data['plan_code'])->first()
            : null;

        $tenant = DB::connection('central')->transaction(function () use ($data, $dbName, $dbUser, $dbPassword, $plan) {
            $tenant = new Tenant([
                'slug' => $data['slug'],
                'name' => $data['name'],
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

        $tenant->forceFill(['status' => 'trialing'])->save();

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
        $user = User::firstOrCreate(
            ['email' => Str::lower($email)],
            [
                'name' => $name ?? $email,
                'password' => Hash::make(Str::password(16)),
                'locale' => $tenant->locale,
                'timezone' => $tenant->timezone,
            ],
        );

        TenantMembership::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
            ],
            [
                'role' => 'owner',
                'joined_at' => now(),
            ],
        );
    }

    private function validate(array $input): array
    {
        $validator = validator($input, [
            'slug' => ['required', 'string', 'min:3', 'max:63',
                'regex:/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])?$/',
                Rule::unique(Tenant::class, 'slug')],
            'name' => ['required', 'string', 'min:2', 'max:255'],
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
            'country' => 'PL',
            'locale' => 'pl',
            'timezone' => 'Europe/Warsaw',
            'currency' => 'PLN',
        ], $validator->validated());
    }
}
