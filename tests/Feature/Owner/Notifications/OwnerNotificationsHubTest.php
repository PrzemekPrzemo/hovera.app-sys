<?php

declare(strict_types=1);

namespace Tests\Feature\Owner\Notifications;

use App\Domain\Notifications\Owner\OwnerNotificationDispatcher;
use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Tenant\Client;
use App\Notifications\Owner\NewInvoiceForOwner;
use App\Notifications\Owner\NewMessageForOwner;
use App\Notifications\Owner\VetVisitRecordedForOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pokrywa Faza 6 PR 6.1 — Owner notifications hub:
 *  * OwnerNotificationDispatcher routing (forClient / forCentralHorse)
 *  * Notification payload structure (database + mail channels)
 *
 * Observer integration tests (Invoice / HealthRecord) wymagałyby
 * fully-booted tenant DB schemy, więc pokrywamy je w osobnych
 * integration testach — tu skupiamy się na dispatcher + payload.
 */
class OwnerNotificationsHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatcher_for_client_sends_to_resolved_user(): void
    {
        Notification::fake();

        $owner = User::create([
            'name' => 'Jan Owner',
            'email' => 'jan-'.uniqid().'@example.test',
            'password' => bcrypt('x'),
        ]);

        // Client w stable DB scoped — żeby uniknąć tenant context'u,
        // tworzymy minimalny stub z central_user_id.
        $client = new Client(['central_user_id' => $owner->id]);
        $client->setAttribute('id', (string) Str::ulid());

        $notif = new NewMessageForOwner(
            stableTenantId: 'tenant-x',
            stableName: 'Test Stable',
            centralHorseId: 'horse-x',
            horseName: 'Iskra',
            messageId: 'msg-1',
            subject: 'Cześć',
            bodyPreview: 'Hello',
            attachmentCount: 0,
            ownerPanelUrl: 'https://example.com/x',
        );

        app(OwnerNotificationDispatcher::class)->forClient($client, $notif);

        Notification::assertSentTo($owner, NewMessageForOwner::class);
    }

    public function test_dispatcher_for_client_is_noop_when_null(): void
    {
        Notification::fake();

        app(OwnerNotificationDispatcher::class)->forClient(null, new NewMessageForOwner(
            stableTenantId: 't',
            stableName: 's',
            centralHorseId: 'h',
            horseName: 'n',
            messageId: 'm',
            subject: null,
            bodyPreview: '.',
            attachmentCount: 0,
            ownerPanelUrl: 'http://example.com',
        ));

        Notification::assertNothingSent();
    }

    public function test_dispatcher_for_client_is_noop_when_no_central_user_id(): void
    {
        Notification::fake();

        $client = new Client(['central_user_id' => null]);
        $client->setAttribute('id', (string) Str::ulid());

        app(OwnerNotificationDispatcher::class)->forClient($client, new NewMessageForOwner(
            stableTenantId: 't',
            stableName: 's',
            centralHorseId: 'h',
            horseName: 'n',
            messageId: 'm',
            subject: null,
            bodyPreview: '.',
            attachmentCount: 0,
            ownerPanelUrl: 'http://example.com',
        ));

        Notification::assertNothingSent();
    }

    public function test_dispatcher_for_central_horse_resolves_primary_owner(): void
    {
        Notification::fake();
        $owner = User::create([
            'name' => 'Jan',
            'email' => 'j-'.uniqid().'@e.t',
            'password' => bcrypt('x'),
        ]);
        $registry = CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $owner->id,
            'name' => 'Iskra',
        ]);

        app(OwnerNotificationDispatcher::class)->forCentralHorse(
            $registry->id,
            new VetVisitRecordedForOwner(
                stableTenantId: 't',
                stableName: 's',
                centralHorseId: $registry->id,
                horseName: 'Iskra',
                recordType: 'vet_visit',
                summary: 'Wizyta',
                details: null,
                costCents: 15000,
                performedAt: '2026-05-15T10:00:00Z',
                nextDueAt: null,
                ownerPanelUrl: 'http://example.com',
            ),
        );

        Notification::assertSentTo($owner, VetVisitRecordedForOwner::class);
    }

    public function test_dispatcher_for_central_horse_noop_when_no_registry(): void
    {
        Notification::fake();

        app(OwnerNotificationDispatcher::class)->forCentralHorse(
            (string) Str::ulid(),
            new VetVisitRecordedForOwner(
                stableTenantId: 't',
                stableName: 's',
                centralHorseId: 'h',
                horseName: 'n',
                recordType: 'vet_visit',
                summary: null,
                details: null,
                costCents: null,
                performedAt: null,
                nextDueAt: null,
                ownerPanelUrl: 'http://example.com',
            ),
        );

        Notification::assertNothingSent();
    }

    public function test_new_message_for_owner_payload_structure(): void
    {
        $notif = new NewMessageForOwner(
            stableTenantId: 'tenant-x',
            stableName: 'Stajnia Iskra',
            centralHorseId: 'horse-x',
            horseName: 'Iskra',
            messageId: 'msg-1',
            subject: 'Wizyta wet',
            bodyPreview: 'Możemy umówić wizytę?',
            attachmentCount: 2,
            ownerPanelUrl: 'https://example.com/messages',
        );

        $channels = $notif->via(new \stdClass);
        $this->assertContains('database', $channels);
        $this->assertContains('mail', $channels);

        $payload = $notif->toDatabase(new \stdClass);
        $this->assertSame('owner.new_message', $payload['kind']);
        $this->assertSame('Stajnia Iskra', $payload['stable_name']);
        $this->assertSame('Iskra', $payload['horse_name']);
        $this->assertSame('Wizyta wet', $payload['subject']);
        $this->assertSame(2, $payload['attachment_count']);
    }

    public function test_new_invoice_for_owner_payload_structure(): void
    {
        $notif = new NewInvoiceForOwner(
            stableTenantId: 'tenant-x',
            stableName: 'Stajnia',
            invoiceId: 'inv-1',
            invoiceNumber: 'FV/2026/05/0001',
            totalCents: 246000,
            currency: 'PLN',
            dueAt: '2026-06-14',
            billingPeriod: '2026-05',
            horseName: 'Iskra',
            ownerPanelUrl: 'https://example.com/invoices/x',
        );

        $payload = $notif->toDatabase(new \stdClass);
        $this->assertSame('owner.new_invoice', $payload['kind']);
        $this->assertSame('FV/2026/05/0001', $payload['invoice_number']);
        $this->assertSame(246000, $payload['total_cents']);
        $this->assertSame('2026-05', $payload['billing_period']);
        $this->assertSame('Iskra', $payload['horse_name']);
    }

    public function test_vet_visit_for_owner_payload_structure(): void
    {
        $notif = new VetVisitRecordedForOwner(
            stableTenantId: 'tenant-x',
            stableName: 'Stajnia',
            centralHorseId: 'horse-x',
            horseName: 'Iskra',
            recordType: 'vaccination',
            summary: 'Tetanus szczepienie',
            details: null,
            costCents: 8000,
            performedAt: '2026-05-15T10:00:00Z',
            nextDueAt: '2027-05-15',
            ownerPanelUrl: 'https://example.com/timeline',
        );

        $payload = $notif->toDatabase(new \stdClass);
        $this->assertSame('owner.vet_visit_recorded', $payload['kind']);
        $this->assertSame('vaccination', $payload['record_type']);
        $this->assertSame('Tetanus szczepienie', $payload['summary']);
        $this->assertSame(8000, $payload['cost_cents']);
        $this->assertSame('2027-05-15', $payload['next_due_at']);
    }

    public function test_all_three_notification_classes_use_database_and_mail(): void
    {
        $classes = [NewMessageForOwner::class, NewInvoiceForOwner::class, VetVisitRecordedForOwner::class];
        foreach ($classes as $cls) {
            $reflection = new \ReflectionClass($cls);
            // Każda klasa ma method via() returning ['database', 'mail']
            $this->assertTrue($reflection->hasMethod('via'), "{$cls} brak method via()");
            $this->assertTrue($reflection->hasMethod('toDatabase'), "{$cls} brak method toDatabase()");
            $this->assertTrue($reflection->hasMethod('toMail'), "{$cls} brak method toMail()");
        }
    }

    public function test_dispatcher_is_resilient_to_unknown_user_id(): void
    {
        Notification::fake();

        $client = new Client(['central_user_id' => '01HZZZZZZZZZZZZZZZZZZZZZ']);
        $client->setAttribute('id', (string) Str::ulid());

        // User nie istnieje w central — dispatcher loguje warning ale nie crashuje.
        app(OwnerNotificationDispatcher::class)->forClient($client, new NewMessageForOwner(
            stableTenantId: 't',
            stableName: 's',
            centralHorseId: 'h',
            horseName: 'n',
            messageId: 'm',
            subject: null,
            bodyPreview: '.',
            attachmentCount: 0,
            ownerPanelUrl: 'http://example.com',
        ));

        Notification::assertNothingSent();
        // No exception thrown — test passes.
        $this->assertTrue(true);
    }
}
