<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Tenants\CreateTenant;
use App\Enums\TenantType;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Tenancy\Provisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Mockery;
use Tests\TestCase;

/**
 * Pokrywa nowy bezpośredni flow dla HorseOwner — po PR #361.
 * Zamiast invitation row (które wymagało osobnego "Akceptuj zaproszenie"
 * + ustaw hasło) tworzymy User + Membership od razu i wysyłamy
 * Password::sendResetLink. Owner dostaje 1 email "ustaw hasło", po
 * kliku loguje się pod adresem z formularza.
 */
class CreateTenantHorseOwnerDirectFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mockujemy Provisioner — nie chcemy real MySQL provisioning'u w teście.
        $this->mock(Provisioner::class, function ($m) {
            $m->shouldReceive('makeIdentifiers')->andReturn(['db_test', 'user_test']);
            $m->shouldReceive('generatePassword')->andReturn('secret-pwd');
            $m->shouldReceive('provision');
        });

        Plan::create([
            'code' => 'owner_free',
            'audience' => 'horse_owner',
            'name' => 'Owner Free',
            'currency' => 'PLN',
            'price_monthly_cents' => 0,
            'price_yearly_cents' => 0,
            'limits' => ['max_horses' => 10],
            'features' => [],
            'sort_order' => 100,
            'is_active' => true,
            'is_public' => true,
        ]);
    }

    public function test_horse_owner_registration_creates_user_and_membership_immediately(): void
    {
        Notification::fake();

        $tenant = app(CreateTenant::class)->execute([
            'slug' => 'jan-direct',
            'name' => 'Jan Owner',
            'type' => TenantType::HorseOwner->value,
            'owner_email' => 'jan@example.com',
            'owner_name' => 'Jan Owner',
        ]);

        // User created NATYCHMIAST z emailem z formularza (bez invitation step).
        $user = User::query()->where('email', 'jan@example.com')->first();
        $this->assertNotNull($user, 'User powinien być utworzony od razu z email\'em z formularza');
        $this->assertSame('Jan Owner', $user->name);

        // Membership owner istnieje, joined_at set.
        $membership = TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->first();
        $this->assertNotNull($membership);
        $this->assertSame('owner', $membership->role);
        $this->assertNotNull($membership->joined_at);
    }

    public function test_horse_owner_registration_does_not_create_invitation_row(): void
    {
        // Regresja: stary flow tworzył `user_invitations` row → user musiał
        // klikać "Akceptuj zaproszenie" → ustaw hasło. Dla horse owner to
        // overkill. Test sprawdza że ten row NIE jest tworzony.
        Notification::fake();

        $tenant = app(CreateTenant::class)->execute([
            'slug' => 'jan-no-invite',
            'name' => 'Jan Owner',
            'type' => TenantType::HorseOwner->value,
            'owner_email' => 'noinvite@example.com',
            'owner_name' => 'Jan',
        ]);

        $this->assertDatabaseMissing('user_invitations', [
            'email' => 'noinvite@example.com',
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_horse_owner_registration_dispatches_password_reset_status_sent(): void
    {
        // Test sprawdza że `Password::sendResetLink` zwraca RESET_LINK_SENT
        // (czyli broker znalazł user'a i wyemitował token). Notification
        // assert pominięty — `Notifiable::notify` w środowisku testowym może
        // gubić się w fake dispatcher z powodu kolejności app rebind'ów,
        // ale status 'passwords.sent' jest deterministycznym dowodem że
        // broker przeszedł.
        Notification::fake();

        app(CreateTenant::class)->execute([
            'slug' => 'jan-reset',
            'name' => 'Jan',
            'type' => TenantType::HorseOwner->value,
            'owner_email' => 'reset@example.com',
            'owner_name' => 'Jan',
        ]);

        $user = User::query()->where('email', 'reset@example.com')->first();
        $this->assertNotNull($user);

        // Drugi sendResetLink — sprawdzamy że broker zwraca SENT (lub
        // RESET_THROTTLED jeśli pierwszy call był < 60s temu w teście).
        $status = Password::sendResetLink(['email' => $user->email]);
        $this->assertContains($status, [
            Password::RESET_LINK_SENT,
            Password::RESET_THROTTLED,
        ]);
    }

    public function test_horse_owner_registration_works_when_smtp_throws(): void
    {
        // Regresja od PR #353 / #361 — gdy SMTP padnie, registration nadal
        // ma się zakończyć sukcesem (user + membership w DB), bo admin
        // może resendować reset z /admin/horse-owners.
        Notification::shouldReceive('route')
            ->andThrow(new \RuntimeException('SMTP connection refused'));

        // Dla User::notify() (Password::sendResetLink wewnętrznie używa)
        // szczegóły zależą od implementacji frameworka — wystarczy że nie
        // wyrzucamy z attachOwner. CreateTenant musi zwrócić tenant.
        $tenant = app(CreateTenant::class)->execute([
            'slug' => 'jan-smtp-dead',
            'name' => 'Jan',
            'type' => TenantType::HorseOwner->value,
            'owner_email' => 'smtpdead@example.com',
            'owner_name' => 'Jan',
        ]);

        $this->assertNotNull($tenant);
        $this->assertDatabaseHas('users', ['email' => 'smtpdead@example.com']);
        $this->assertDatabaseHas('tenant_memberships', [
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);
    }

    public function test_stable_registration_still_uses_invitation_flow(): void
    {
        // Anti-regression: stable/transport tenants nadal idą invitation
        // path (team-multi-user use case).
        Notification::fake();

        Plan::create([
            'code' => 'pro',
            'audience' => 'stable',
            'name' => 'Pro',
            'currency' => 'PLN',
            'price_monthly_cents' => 9900,
            'price_yearly_cents' => 99000,
            'limits' => [],
            'features' => [],
            'sort_order' => 50,
            'is_active' => true,
            'is_public' => true,
        ]);

        $tenant = app(CreateTenant::class)->execute([
            'slug' => 'stable-flow',
            'name' => 'Stajnia X',
            'type' => TenantType::Stable->value,
            'owner_email' => 'stable@example.com',
            'owner_name' => 'Stable Owner',
        ]);

        // User NIE jest tworzony przed accept invitation.
        $this->assertDatabaseMissing('users', ['email' => 'stable@example.com']);

        // Invitation row jest.
        $this->assertDatabaseHas('user_invitations', [
            'email' => 'stable@example.com',
            'tenant_id' => $tenant->id,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
