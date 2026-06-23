<?php

declare(strict_types=1);

namespace Tests\Feature\Specialist;

use App\Models\Central\ExternalSpecialist;
use App\Models\Central\SpecialistMessage;
use App\Models\Central\SpecialistThread;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Notifications\Specialist\NewSpecialistMessageNotification;
use App\Services\Specialist\SpecialistMessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * PR O5 Channel B (epic 1.4/1.5) — SpecialistMessagingService:
 * zakładanie wątków, odpowiedzi, mark-read, powiadomienia drugiej strony.
 */
class SpecialistMessagingServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private ExternalSpecialist $specialist;

    private User $staffA;

    private User $staffB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->makeTenant();
        $this->specialist = ExternalSpecialist::create([
            'email' => 'vet@example.com',
            'display_name' => 'dr Vet',
            'specialty' => 'vet',
        ]);

        $this->staffA = User::create(['name' => 'Anna', 'email' => 'anna@example.com', 'password' => bcrypt('x')]);
        $this->staffB = User::create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => bcrypt('x')]);

        foreach ([$this->staffA, $this->staffB] as $u) {
            TenantMembership::create(['tenant_id' => $this->tenant->id, 'user_id' => $u->id, 'role' => 'manager']);
        }
    }

    public function test_start_thread_creates_thread_and_first_message(): void
    {
        Notification::fake();

        $thread = $this->service()->startThread(
            $this->tenant,
            $this->specialist,
            'Kontrola',
            SpecialistMessage::SENDER_TENANT_USER,
            $this->staffA->id,
            'Dzień dobry, prosimy o wizytę.',
            horseId: '01HHORSE000000000000000001',
        );

        $this->assertDatabaseHas('specialist_threads', ['id' => $thread->id, 'subject' => 'Kontrola']);
        $this->assertSame(1, $thread->messages()->count());
        $this->assertNotNull($thread->fresh()->last_message_at);
        $this->assertSame('01HHORSE000000000000000001', $thread->horse_id);
    }

    public function test_tenant_reply_notifies_specialist_only(): void
    {
        Notification::fake();
        $thread = $this->makeThread();

        $this->service()->reply($thread, SpecialistMessage::SENDER_TENANT_USER, $this->staffA->id, 'pytanie');

        Notification::assertSentTo($this->specialist, NewSpecialistMessageNotification::class);
        Notification::assertNotSentTo([$this->staffA], NewSpecialistMessageNotification::class);
        Notification::assertNotSentTo([$this->staffB], NewSpecialistMessageNotification::class);
    }

    public function test_specialist_reply_notifies_all_tenant_members(): void
    {
        Notification::fake();
        $thread = $this->makeThread();

        $this->service()->reply($thread, SpecialistMessage::SENDER_SPECIALIST, $this->specialist->id, 'odpowiedź');

        Notification::assertSentTo($this->staffA, NewSpecialistMessageNotification::class);
        Notification::assertSentTo($this->staffB, NewSpecialistMessageNotification::class);
        Notification::assertNotSentTo([$this->specialist], NewSpecialistMessageNotification::class);
    }

    public function test_specialist_gets_mail_only_staff_gets_mail_and_database(): void
    {
        Notification::fake();
        $thread = $this->makeThread();

        $this->service()->reply($thread, SpecialistMessage::SENDER_TENANT_USER, $this->staffA->id, 'do veta');
        Notification::assertSentTo(
            $this->specialist,
            NewSpecialistMessageNotification::class,
            fn ($n, array $channels) => $channels === ['mail'],
        );

        $this->service()->reply($thread, SpecialistMessage::SENDER_SPECIALIST, $this->specialist->id, 'do stajni');
        Notification::assertSentTo(
            $this->staffA,
            NewSpecialistMessageNotification::class,
            fn ($n, array $channels) => in_array('database', $channels, true) && in_array('mail', $channels, true),
        );
    }

    public function test_mark_read_marks_only_opposite_party_messages(): void
    {
        Notification::fake();
        $thread = $this->makeThread();

        $this->service()->reply($thread, SpecialistMessage::SENDER_TENANT_USER, $this->staffA->id, 'a');
        $this->service()->reply($thread, SpecialistMessage::SENDER_SPECIALIST, $this->specialist->id, 'b');

        // Specjalista czyta → tylko wiadomości stajni (tenant_user) dostają read_at.
        $marked = $this->service()->markRead($thread, SpecialistMessage::SENDER_SPECIALIST);

        $this->assertSame(1, $marked);
        $this->assertSame(
            0,
            SpecialistMessage::query()->where('thread_id', $thread->id)->unreadFor(SpecialistMessage::SENDER_SPECIALIST)->count(),
        );
        // Wiadomość specjalisty nadal nieprzeczytana dla stajni.
        $this->assertSame(
            1,
            SpecialistMessage::query()->where('thread_id', $thread->id)->unreadFor(SpecialistMessage::SENDER_TENANT_USER)->count(),
        );
    }

    private function service(): SpecialistMessagingService
    {
        return app(SpecialistMessagingService::class);
    }

    private function makeThread(): SpecialistThread
    {
        return SpecialistThread::create([
            'tenant_id' => $this->tenant->id,
            'specialist_id' => $this->specialist->id,
            'subject' => 'Wątek',
        ]);
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();
        $tenant = new Tenant([
            'slug' => 'msg-'.$u,
            'name' => 'Stajnia',
            'db_name' => 'msg_'.$u,
            'db_username' => 'msg_'.substr($u, -8),
            'status' => 'active',
            'settings' => [],
        ]);
        $tenant->db_password = 'irrelevant';
        $tenant->save();

        return $tenant;
    }
}
