<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\Notifications\TransporterRejectedNotification;
use App\Domain\Transport\Notifications\TransporterVerifiedNotification;
use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Filament\Admin\Resources\TransporterResource;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Mockery\MockInterface;
use Tests\TestCase;

class AdminTransporterVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(\App\Services\MasterAuditLogger::class, function (MockInterface $m) {
            $m->shouldReceive('record')->andReturnNull();
        });
    }

    public function test_resource_routes_registered(): void
    {
        $names = collect(app('router')->getRoutes())->map(fn ($r) => $r->getName())->filter()->values();
        $this->assertTrue($names->contains('filament.admin.resources.transporters.index'));
        $this->assertTrue($names->contains('filament.admin.resources.transporters.edit'));
    }

    public function test_eloquent_query_only_returns_transporters(): void
    {
        $stable = $this->makeTenant(TenantType::Stable, null);
        $tr = $this->makeTenant(TenantType::Transporter, VerificationStatus::Pending);

        $ids = TransporterResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($tr->id, $ids);
        $this->assertNotContains($stable->id, $ids);
    }

    public function test_verify_flips_status_and_notifies_owner(): void
    {
        NotificationFacade::fake();
        $admin = User::create([
            'email' => 'admin@hovera.app',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
            'is_master_admin' => true,
        ]);
        Auth::setUser($admin);

        [$tenant, $ownerEmail] = $this->makeTransporterWithOwner(VerificationStatus::UnderReview);

        TransporterResource::verify($tenant, 'Wszystko OK, dokumenty zatwierdzone.');

        $fresh = $tenant->fresh();
        $this->assertSame(VerificationStatus::Verified, $fresh->verification_status);
        $this->assertNotNull($fresh->verified_at);
        $this->assertSame($admin->id, $fresh->verified_by_user_id);
        $this->assertSame('Wszystko OK, dokumenty zatwierdzone.', $fresh->verification_notes);

        NotificationFacade::assertSentOnDemand(
            TransporterVerifiedNotification::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === $ownerEmail,
        );
    }

    public function test_reject_flips_status_and_sends_reason(): void
    {
        NotificationFacade::fake();
        $admin = User::create([
            'email' => 'admin@hovera.app',
            'name' => 'Admin',
            'password' => bcrypt('secret'),
            'is_master_admin' => true,
        ]);
        Auth::setUser($admin);

        [$tenant, $ownerEmail] = $this->makeTransporterWithOwner(VerificationStatus::UnderReview);
        $reason = 'Świadectwo transportu zwierząt nieaktualne — wgraj nową wersję.';

        TransporterResource::reject($tenant, $reason);

        $fresh = $tenant->fresh();
        $this->assertSame(VerificationStatus::Rejected, $fresh->verification_status);
        $this->assertNull($fresh->verified_at);
        $this->assertSame($reason, $fresh->verification_notes);

        NotificationFacade::assertSentOnDemand(
            TransporterRejectedNotification::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === $ownerEmail
                && str_contains($n->reason, 'Świadectwo'),
        );
    }

    public function test_verify_without_owner_email_does_not_throw(): void
    {
        NotificationFacade::fake();
        $admin = User::create([
            'email' => 'admin2@hovera.app', 'name' => 'A', 'password' => bcrypt('s'), 'is_master_admin' => true,
        ]);
        Auth::setUser($admin);

        // Tenant bez membership owner — notyfikacja nie powinna polecieć,
        // ale akcja nie może rzucać exception.
        $tenant = $this->makeTenant(TenantType::Transporter, VerificationStatus::UnderReview);

        TransporterResource::verify($tenant, '');

        $this->assertSame(VerificationStatus::Verified, $tenant->fresh()->verification_status);
        NotificationFacade::assertNothingSent();
    }

    /** @return array{0: Tenant, 1: string} */
    private function makeTransporterWithOwner(VerificationStatus $status): array
    {
        $tenant = $this->makeTenant(TenantType::Transporter, $status);
        $email = 'owner-'.uniqid().'@example.com';
        $owner = User::create([
            'email' => $email,
            'name' => 'Owner',
            'password' => bcrypt('secret'),
        ]);
        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        return [$tenant, $email];
    }

    private function makeTenant(TenantType $type, ?VerificationStatus $vs): Tenant
    {
        return Tenant::create([
            'slug' => 't-'.uniqid(),
            'name' => 'Test',
            'type' => $type,
            'verification_status' => $vs,
            'db_name' => 't_'.uniqid(),
            'db_username' => 't_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }
}
