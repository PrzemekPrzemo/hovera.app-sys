<?php

declare(strict_types=1);

namespace Tests\Feature\Specialist;

use App\Models\Central\ExternalSpecialist;
use App\Models\Central\OwnerSpecialistMessage;
use App\Models\Central\OwnerSpecialistThread;
use App\Models\Central\User;
use App\Notifications\Specialist\NewOwnerSpecialistMessageNotification;
use App\Services\Specialist\OwnerSpecialistMessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * PR O5 Channel D (epic 3) — OwnerSpecialistMessagingService: wątki
 * właściciel ↔ specjalista, odpowiedzi, mark-read, powiadomienia.
 */
class OwnerSpecialistMessagingServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private ExternalSpecialist $specialist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create(['name' => 'Olga Owner', 'email' => 'olga@example.com', 'password' => bcrypt('x')]);
        $this->specialist = ExternalSpecialist::create([
            'email' => 'vet@example.com',
            'display_name' => 'dr Vet',
            'specialty' => 'vet',
        ]);
    }

    public function test_start_thread_with_explicit_horse(): void
    {
        Notification::fake();

        $thread = $this->service()->startThread(
            $this->owner,
            $this->specialist,
            'Konsultacja',
            OwnerSpecialistMessage::SENDER_OWNER,
            $this->owner->id,
            'Dzień dobry',
            horseId: '01HHORSE000000000000000009',
        );

        $this->assertDatabaseHas('owner_specialist_threads', [
            'id' => $thread->id,
            'owner_user_id' => $this->owner->id,
            'horse_id' => '01HHORSE000000000000000009',
        ]);
        $this->assertSame(1, $thread->messages()->count());
    }

    public function test_owner_reply_notifies_specialist(): void
    {
        Notification::fake();
        $thread = $this->makeThread();

        $this->service()->reply($thread, OwnerSpecialistMessage::SENDER_OWNER, $this->owner->id, 'pytanie');

        Notification::assertSentTo($this->specialist, NewOwnerSpecialistMessageNotification::class,
            fn ($n, array $channels) => $channels === ['mail']);
        Notification::assertNotSentTo([$this->owner], NewOwnerSpecialistMessageNotification::class);
    }

    public function test_specialist_reply_notifies_owner_with_database(): void
    {
        Notification::fake();
        $thread = $this->makeThread();

        $this->service()->reply($thread, OwnerSpecialistMessage::SENDER_SPECIALIST, $this->specialist->id, 'odpowiedź');

        Notification::assertSentTo($this->owner, NewOwnerSpecialistMessageNotification::class,
            fn ($n, array $channels) => in_array('database', $channels, true) && in_array('mail', $channels, true));
        Notification::assertNotSentTo([$this->specialist], NewOwnerSpecialistMessageNotification::class);
    }

    public function test_mark_read_marks_only_opposite_party(): void
    {
        Notification::fake();
        $thread = $this->makeThread();

        $this->service()->reply($thread, OwnerSpecialistMessage::SENDER_OWNER, $this->owner->id, 'a');
        $this->service()->reply($thread, OwnerSpecialistMessage::SENDER_SPECIALIST, $this->specialist->id, 'b');

        $marked = $this->service()->markRead($thread, OwnerSpecialistMessage::SENDER_OWNER);

        $this->assertSame(1, $marked);
        $this->assertSame(0, OwnerSpecialistMessage::query()->where('thread_id', $thread->id)
            ->unreadFor(OwnerSpecialistMessage::SENDER_OWNER)->count());
        $this->assertSame(1, OwnerSpecialistMessage::query()->where('thread_id', $thread->id)
            ->unreadFor(OwnerSpecialistMessage::SENDER_SPECIALIST)->count());
    }

    private function service(): OwnerSpecialistMessagingService
    {
        return app(OwnerSpecialistMessagingService::class);
    }

    private function makeThread(): OwnerSpecialistThread
    {
        return OwnerSpecialistThread::create([
            'owner_user_id' => $this->owner->id,
            'specialist_id' => $this->specialist->id,
            'subject' => 'Wątek',
        ]);
    }
}
