<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Actions\Invitations\SendInvitation;
use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Tenancy\Provisioner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
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
        //   transporter → transport_start (4 pojazdy / 4 kierowców) — wybrany
        //                 jako najtańszy plan transport_* by domyślnie nie
        //                 fakturować trialowca więcej niż konieczne. Po
        //                 weryfikacji owner może zmienić plan.
        // Caller może override przez plan_code.
        $defaultPlanCode = match ($type) {
            TenantType::Stable => 'pro',
            TenantType::Transporter => 'transport_start',
            // Owner = FREE forever, brak płatnego planu do wybrania.
            TenantType::HorseOwner => 'owner_free',
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
                    ? VerificationStatus::Pending
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
        //
        // TRANSPORTER trial flow (marketing spec — hovera.app/produkt/transport/):
        //   "1 miesiąc gratis NIE od signupu — od momentu pozytywnej weryfikacji
        //    dokumentów (2-5 dni roboczych)". Dlatego trial_ends_at zostaje
        //    NULL aż do `TransporterResource::verify()` → `startTrialOnVerification()`.
        //   Status przez ten czas = 'provisioning' (tenant nie może jeszcze
        //    wystawiać ofert ani być billable'em).
        if ($type === TenantType::Stable) {
            $postProvisionAttrs = [
                'status' => 'trialing',
                'trial_ends_at' => $tenant->trial_ends_at ?? now()->addDays(30),
                'trial_max_horses' => $tenant->trial_max_horses ?? 10,
                'trial_max_clients' => $tenant->trial_max_clients ?? 5,
            ];
        } elseif ($type === TenantType::HorseOwner) {
            // Owner = FREE forever, brak trial'a (od razu active). Status
            // 'active' (a nie 'trialing') żeby billing middleware
            // (RedirectIfTrialExpired itd.) wiedział że nie ma czego pilnować.
            $postProvisionAttrs = [
                'status' => 'active',
                'trial_ends_at' => null,
            ];
        } else {
            // Transporter — czekamy na weryfikację. Status pozostaje
            // 'provisioning' (LoginController odsyła do /transport/verification
            // page do czasu flipu na 'trialing' w startTrialOnVerification()).
            $postProvisionAttrs = [
                'status' => 'provisioning',
                'trial_ends_at' => null,
            ];
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

        // Horse owner: 1 email = 1 konto, no team. Pomijamy invitation flow
        // (który był designed dla wieloużytkownikowych team'ów stable/transport).
        // Tworzymy User'a od razu z losowym hasłem + Password::sendResetLink —
        // właściciel dostaje 1 mail "ustaw hasło", po klikknięciu loguje się
        // pod adresem z formularza rejestracji. Brak step'u "akceptuj zaproszenie".
        //
        // Patrz docs/OWNER-STABLE-ROADMAP.md "self-service rejestracja" + PR #361.
        if ($tenant->type === TenantType::HorseOwner) {
            $this->attachHorseOwnerDirectly($tenant, $email, $name);

            return;
        }

        // New user (stable/transport) — send invitation. Membership na accept.
        // Tu invitation flow ma sens bo team'y multi-user (manager zaprasza
        // instruktora/vet'a/employee'go), name kolizji email + role.
        app(SendInvitation::class)->execute(
            email: $email,
            tenant: $tenant,
            role: 'owner',
            name: $name,
        );
    }

    /**
     * Horse owner registration path — tworzy User + Membership od razu +
     * wysyła password reset link. Bez invitation row, bez accept step.
     *
     * Mail dispatch jest soft-fail (analogicznie do SendInvitation #353):
     * jeśli SMTP padnie, tenant + user + membership pozostają w DB,
     * master admin może resendować password reset z /admin/horse-owners
     * (action `force_password_reset`).
     */
    private function attachHorseOwnerDirectly(Tenant $tenant, string $email, ?string $name): void
    {
        // Random password — user go nie zna, nigdy nie używa. Reset link
        // z `Password::sendResetLink()` ustanowi własne hasło.
        $user = User::create([
            'name' => $name ?? Str::before($email, '@'),
            'email' => $email,
            'password' => Hash::make(Str::random(64)),
        ]);

        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        try {
            Password::sendResetLink(['email' => $email]);
        } catch (Throwable $e) {
            // SMTP padło — user + membership są w DB, admin resendnie ręcznie.
            // NIE rzucamy: chcemy żeby HorseOwnerRegistrationController dotarł
            // do thanks page (parity z SendInvitation soft-fail behavior).
            report($e);
            Log::warning('Horse owner password reset mail failed (soft-fail)', [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function validate(array $input): array
    {
        $validator = validator($input, [
            'slug' => ['required', 'string', 'min:3', 'max:63',
                'regex:/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])?$/',
                Rule::unique(Tenant::class, 'slug')],
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'type' => ['sometimes', 'string', Rule::in(['stable', 'transporter', 'horse_owner'])],
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
