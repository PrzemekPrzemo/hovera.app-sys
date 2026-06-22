<?php

declare(strict_types=1);

namespace Tests\Feature\Specialist;

use App\Domain\Specialists\SpecialistInviteService;
use App\Enums\TenantType;
use App\Models\Central\ExternalSpecialist;
use App\Models\Central\SpecialistMagicLink;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Notifications\Specialist\SpecialistInvitationNotification;
use App\Services\TenantAuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Mockery\MockInterface;
use Tests\TestCase;

class SpecialistInviteServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->mock(TenantAuditLogger::class, function (MockInterface $m) {
            $m->shouldReceive('record')->andReturnNull();
        });

        $this->tenant = $this->makeTenant();
        $this->user = $this->makeUser();
    }

    public function test_invite_creates_new_specialist_when_email_unknown(): void
    {
        $result = app(SpecialistInviteService::class)->invite(
            email: 'newvet@example.com',
            displayName: 'dr Anna Vetinari',
            invitingTenant: $this->tenant,
            invitingUser: $this->user,
            extra: ['specialty' => 'vet'],
        );

        $this->assertTrue($result->isNewInvite());
        $this->assertSame('newvet@example.com', $result->specialist->email);
        $this->assertSame('dr Anna Vetinari', $result->specialist->display_name);
        $this->assertSame('vet', $result->specialist->specialty);
        $this->assertSame($this->user->id, $result->specialist->created_by_user_id);

        $this->assertNotNull($result->magicLink);
        $this->assertSame(SpecialistMagicLink::KIND_INITIAL_SETUP, $result->magicLink->kind);
        $this->assertSame($this->tenant->id, $result->magicLink->issued_for_tenant_id);

        Notification::assertSentTo(
            $result->specialist,
            SpecialistInvitationNotification::class,
            fn ($n) => $n->invitingTenantName === $this->tenant->name
                && $n->invitingUserName === $this->user->name
                && $n->plainToken !== '',
        );
    }

    public function test_invite_reissues_link_when_email_known_but_setup_incomplete(): void
    {
        // Pre-existing specialist bez password (np. wcześniejszy invite expired)
        $existing = ExternalSpecialist::create([
            'email' => 'pending@example.com',
            'display_name' => 'dr Pending',
        ]);

        $result = app(SpecialistInviteService::class)->invite(
            email: 'pending@example.com',
            displayName: 'dr Pending (Re-invited)',
            invitingTenant: $this->tenant,
            invitingUser: $this->user,
        );

        $this->assertTrue($result->isReissue());
        $this->assertSame($existing->id, $result->specialist->id);
        $this->assertNotNull($result->magicLink);

        Notification::assertSentTo($existing, SpecialistInvitationNotification::class);
    }

    public function test_invite_skips_link_when_specialist_already_setup(): void
    {
        ExternalSpecialist::create([
            'email' => 'active@example.com',
            'display_name' => 'dr Already',
            'password_hash' => Hash::make('secret'),
            'email_verified_at' => now(),
        ]);

        $result = app(SpecialistInviteService::class)->invite(
            email: 'active@example.com',
            displayName: 'dr Already',
            invitingTenant: $this->tenant,
            invitingUser: $this->user,
        );

        $this->assertTrue($result->isExistingAlreadySetup());
        $this->assertNull($result->magicLink);

        Notification::assertNothingSent();
    }

    public function test_invite_normalizes_email_to_lowercase(): void
    {
        $result = app(SpecialistInviteService::class)->invite(
            email: 'MixedCase@Example.COM',
            displayName: 'dr Mixed',
            invitingTenant: $this->tenant,
            invitingUser: $this->user,
        );

        $this->assertSame('mixedcase@example.com', $result->specialist->email);
    }

    public function test_invite_trims_email_whitespace(): void
    {
        $result = app(SpecialistInviteService::class)->invite(
            email: '  vet-spaces@example.com  ',
            displayName: 'dr Trim',
            invitingTenant: $this->tenant,
            invitingUser: $this->user,
        );

        $this->assertSame('vet-spaces@example.com', $result->specialist->email);
    }

    public function test_invite_creates_unique_magic_link_per_call(): void
    {
        $first = app(SpecialistInviteService::class)->invite(
            email: 'multi@example.com',
            displayName: 'dr Multi',
            invitingTenant: $this->tenant,
            invitingUser: $this->user,
        );

        $second = app(SpecialistInviteService::class)->invite(
            email: 'multi@example.com',
            displayName: 'dr Multi',
            invitingTenant: $this->tenant,
            invitingUser: $this->user,
        );

        $this->assertTrue($first->isNewInvite());
        $this->assertTrue($second->isReissue());
        $this->assertNotSame($first->magicLink->id, $second->magicLink->id);
        $this->assertNotSame($first->magicLink->token_hash, $second->magicLink->token_hash);
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'inv-st-'.$u,
            'name' => 'Invite Stable',
            'type' => TenantType::Stable,
            'db_name' => 'inv_'.$u,
            'db_username' => 'inv_'.substr($u, -8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
            'settings' => [],
        ]);
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'Inviting User',
            'email' => 'inviter-'.uniqid().'@example.com',
            'password' => Hash::make('secret'),
        ]);
    }
}
