<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\Leads\LeadDispatcher;
use App\Domain\Transport\Notifications\LeadReceivedNotification;
use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\TransportLead;
use App\Models\Central\TransportLeadDispatch;
use App\Models\Central\TransportServiceArea;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeadDispatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_broadcast_finds_transporters_by_service_area_voivodeship(): void
    {
        NotificationFacade::fake();
        config()->set('transport.voivodeship_adjacency', [
            'mazowieckie' => ['łódzkie'],
            'łódzkie' => ['mazowieckie'],
        ]);

        // T1 — obsługuje mazowieckie (=pickup)
        [$t1, $t1Email] = $this->makeVerifiedTransporterWithOwner();
        TransportServiceArea::create(['transporter_tenant_id' => $t1->id, 'voivodeship' => 'mazowieckie']);

        // T2 — obsługuje śląskie (poza zasięgiem)
        [$t2] = $this->makeVerifiedTransporterWithOwner();
        TransportServiceArea::create(['transporter_tenant_id' => $t2->id, 'voivodeship' => 'śląskie']);

        $lead = $this->makeLead('mazowieckie', 'małopolskie');

        $result = app(LeadDispatcher::class)->dispatch($lead);

        $this->assertSame(1, $result['notified']);
        $this->assertContains($t1->id, $result['transporter_ids']);
        $this->assertNotContains($t2->id, $result['transporter_ids']);

        NotificationFacade::assertSentOnDemand(
            LeadReceivedNotification::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === $t1Email,
        );
    }

    public function test_broadcast_uses_adjacency_to_widen_search(): void
    {
        NotificationFacade::fake();
        config()->set('transport.voivodeship_adjacency', [
            'mazowieckie' => ['łódzkie', 'podlaskie'],
        ]);

        // Transporter obsługuje tylko łódzkie — lead z mazowieckiego MA TRAFIĆ
        // bo łódzkie jest sąsiednie do mazowieckiego.
        [$t] = $this->makeVerifiedTransporterWithOwner();
        TransportServiceArea::create(['transporter_tenant_id' => $t->id, 'voivodeship' => 'łódzkie']);

        $lead = $this->makeLead('mazowieckie', 'małopolskie');
        $result = app(LeadDispatcher::class)->dispatch($lead);

        $this->assertContains($t->id, $result['transporter_ids']);
    }

    public function test_broadcast_skips_unverified_transporters(): void
    {
        NotificationFacade::fake();
        config()->set('transport.voivodeship_adjacency', []);

        $pending = $this->makeTransporter(VerificationStatus::Pending);
        TransportServiceArea::create(['transporter_tenant_id' => $pending->id, 'voivodeship' => 'mazowieckie']);

        $lead = $this->makeLead('mazowieckie', 'mazowieckie');
        $result = app(LeadDispatcher::class)->dispatch($lead);

        $this->assertSame(0, $result['notified']);
        $this->assertEmpty(TransportLeadDispatch::query()->where('lead_id', $lead->id)->get());
    }

    public function test_direct_mode_uses_targeted_ids_only(): void
    {
        NotificationFacade::fake();
        config()->set('transport.voivodeship_adjacency', []);

        [$picked] = $this->makeVerifiedTransporterWithOwner();
        [$other] = $this->makeVerifiedTransporterWithOwner();
        TransportServiceArea::create(['transporter_tenant_id' => $picked->id, 'voivodeship' => 'mazowieckie']);
        TransportServiceArea::create(['transporter_tenant_id' => $other->id, 'voivodeship' => 'mazowieckie']);

        $lead = $this->makeLead('mazowieckie', 'mazowieckie', mode: 'direct', targeted: [$picked->id]);

        $result = app(LeadDispatcher::class)->dispatch($lead);

        $this->assertSame(1, $result['notified']);
        $this->assertContains($picked->id, $result['transporter_ids']);
        $this->assertNotContains($other->id, $result['transporter_ids']);
    }

    public function test_creates_dispatch_records(): void
    {
        NotificationFacade::fake();
        config()->set('transport.voivodeship_adjacency', []);

        [$t1] = $this->makeVerifiedTransporterWithOwner();
        [$t2] = $this->makeVerifiedTransporterWithOwner();
        foreach ([$t1, $t2] as $t) {
            TransportServiceArea::create(['transporter_tenant_id' => $t->id, 'voivodeship' => 'mazowieckie']);
        }

        $lead = $this->makeLead('mazowieckie', 'mazowieckie');
        app(LeadDispatcher::class)->dispatch($lead);

        $this->assertSame(2, TransportLeadDispatch::query()
            ->where('lead_id', $lead->id)
            ->where('notified_email', true)
            ->whereNotNull('notified_at')
            ->count());
    }

    public function test_dispatch_is_idempotent_on_retry(): void
    {
        NotificationFacade::fake();
        config()->set('transport.voivodeship_adjacency', []);

        [$t] = $this->makeVerifiedTransporterWithOwner();
        TransportServiceArea::create(['transporter_tenant_id' => $t->id, 'voivodeship' => 'mazowieckie']);

        $lead = $this->makeLead('mazowieckie', 'mazowieckie');

        app(LeadDispatcher::class)->dispatch($lead);
        app(LeadDispatcher::class)->dispatch($lead);   // retry

        $this->assertSame(1, TransportLeadDispatch::query()->where('lead_id', $lead->id)->count(),
            'unique (lead_id, transporter_tenant_id) must prevent duplicates');
    }

    public function test_transporter_without_owner_does_not_throw(): void
    {
        NotificationFacade::fake();
        config()->set('transport.voivodeship_adjacency', []);

        $tNoOwner = $this->makeTransporter(VerificationStatus::Verified);
        TransportServiceArea::create(['transporter_tenant_id' => $tNoOwner->id, 'voivodeship' => 'mazowieckie']);

        $lead = $this->makeLead('mazowieckie', 'mazowieckie');
        $result = app(LeadDispatcher::class)->dispatch($lead);

        // Dispatch record stworzony ALE notified_email=false bo brak ownera
        $this->assertSame(0, $result['notified']);
        $this->assertSame(1, TransportLeadDispatch::query()->where('lead_id', $lead->id)->count());
        $this->assertSame(0, TransportLeadDispatch::query()
            ->where('lead_id', $lead->id)
            ->where('notified_email', true)
            ->count());
    }

    /** @return array{0: Tenant, 1: string} */
    private function makeVerifiedTransporterWithOwner(): array
    {
        $tenant = $this->makeTransporter(VerificationStatus::Verified);
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

    private function makeTransporter(VerificationStatus $vs): Tenant
    {
        return Tenant::create([
            'slug' => 't-'.uniqid(),
            'name' => 'Firma',
            'type' => TenantType::Transporter,
            'verification_status' => $vs,
            'db_name' => 't_'.uniqid(),
            'db_username' => 't_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }

    private function makeLead(string $pickupV, string $dropoffV, string $mode = 'broadcast', array $targeted = []): TransportLead
    {
        return TransportLead::create([
            'id' => (string) Str::ulid(),
            'mode' => $mode,
            'targeted_transporter_ids' => $mode === 'direct' ? $targeted : null,
            'pickup_address' => 'Test pickup',
            'pickup_lat' => 0, 'pickup_lng' => 0,
            'pickup_voivodeship' => $pickupV,
            'dropoff_address' => 'Test dropoff',
            'dropoff_lat' => 0, 'dropoff_lng' => 0,
            'dropoff_voivodeship' => $dropoffV,
            'preferred_date' => now()->addDays(5)->toDateString(),
            'horse_count' => 1,
            'status' => 'open',
            'expires_at' => now()->addDays(14),
        ]);
    }
}
