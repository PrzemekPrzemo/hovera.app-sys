<?php

declare(strict_types=1);

namespace Tests\Feature\Specialist;

use App\Models\Central\ExternalSpecialist;
use App\Models\Central\SpecialistMessage;
use App\Models\Central\SpecialistThread;
use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PR O5 Channel B (epic 1.3) — SpecialistThread + SpecialistMessage models.
 */
class SpecialistThreadTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private ExternalSpecialist $specialist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->makeTenant();
        $this->specialist = ExternalSpecialist::create([
            'email' => 'vet@example.com',
            'display_name' => 'dr Vet',
            'specialty' => 'vet',
        ]);
    }

    public function test_thread_relations_resolve(): void
    {
        $thread = $this->makeThread();

        $this->assertSame($this->specialist->id, $thread->specialist->id);
        $this->assertSame($this->tenant->id, $thread->tenant->id);
    }

    public function test_messages_ordered_and_latest(): void
    {
        $thread = $this->makeThread();

        $first = $this->addMessage($thread, SpecialistMessage::SENDER_TENANT_USER, 'pierwsza', '2026-06-01 10:00:00');
        $second = $this->addMessage($thread, SpecialistMessage::SENDER_SPECIALIST, 'druga', '2026-06-02 10:00:00');

        $ordered = $thread->messages()->get();
        $this->assertSame([$first->id, $second->id], $ordered->pluck('id')->all());
        $this->assertSame($second->id, $thread->latestMessage()->first()->id);
    }

    public function test_scopes_filter_by_tenant_and_specialist(): void
    {
        $mine = $this->makeThread();

        $otherTenant = $this->makeTenant();
        SpecialistThread::create([
            'tenant_id' => $otherTenant->id,
            'specialist_id' => $this->specialist->id,
            'subject' => 'inny tenant',
        ]);

        $this->assertSame(
            [$mine->id],
            SpecialistThread::query()->forTenant($this->tenant->id)->pluck('id')->all()
        );
        $this->assertSame(2, SpecialistThread::query()->forSpecialist($this->specialist->id)->count());
    }

    public function test_touch_last_message_updates_timestamp(): void
    {
        $thread = $this->makeThread();
        $this->assertNull($thread->last_message_at);

        $thread->touchLastMessage();

        $this->assertNotNull($thread->fresh()->last_message_at);
    }

    public function test_message_sender_helpers(): void
    {
        $thread = $this->makeThread();
        $fromSpecialist = $this->addMessage($thread, SpecialistMessage::SENDER_SPECIALIST, 'od veta');
        $fromTenant = $this->addMessage($thread, SpecialistMessage::SENDER_TENANT_USER, 'od stajni');

        $this->assertTrue($fromSpecialist->isFromSpecialist());
        $this->assertFalse($fromSpecialist->isFromTenantUser());
        $this->assertTrue($fromTenant->isFromTenantUser());
    }

    public function test_mark_read_is_idempotent(): void
    {
        $thread = $this->makeThread();
        $message = $this->addMessage($thread, SpecialistMessage::SENDER_SPECIALIST, 'hej');

        $message->markRead();
        $first = $message->fresh()->read_at;

        $message->markRead();
        $this->assertEquals($first, $message->fresh()->read_at);
    }

    public function test_unread_for_counts_only_opposite_party_messages(): void
    {
        $thread = $this->makeThread();

        // Wiadomości od stajni → nieprzeczytane DLA specjalisty.
        $this->addMessage($thread, SpecialistMessage::SENDER_TENANT_USER, 'a');
        $this->addMessage($thread, SpecialistMessage::SENDER_TENANT_USER, 'b');
        // Wiadomość od specjalisty → NIE liczy się jako unread dla specjalisty.
        $this->addMessage($thread, SpecialistMessage::SENDER_SPECIALIST, 'c');

        $unreadForSpecialist = SpecialistMessage::query()
            ->where('thread_id', $thread->id)
            ->unreadFor(SpecialistMessage::SENDER_SPECIALIST)
            ->count();
        $unreadForTenant = SpecialistMessage::query()
            ->where('thread_id', $thread->id)
            ->unreadFor(SpecialistMessage::SENDER_TENANT_USER)
            ->count();

        $this->assertSame(2, $unreadForSpecialist);
        $this->assertSame(1, $unreadForTenant);
    }

    private function makeThread(): SpecialistThread
    {
        return SpecialistThread::create([
            'tenant_id' => $this->tenant->id,
            'specialist_id' => $this->specialist->id,
            'subject' => 'Kontrola zdrowia',
        ]);
    }

    private function addMessage(
        SpecialistThread $thread,
        string $senderType,
        string $body,
        ?string $createdAt = null,
    ): SpecialistMessage {
        $message = SpecialistMessage::create([
            'thread_id' => $thread->id,
            'sender_type' => $senderType,
            'sender_id' => '01HSENDER000000000000000001',
            'body' => $body,
        ]);

        if ($createdAt !== null) {
            $message->forceFill(['created_at' => $createdAt])->save();
        }

        return $message;
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();
        $tenant = new Tenant([
            'slug' => 'thr-'.$u,
            'name' => 'Stajnia',
            'db_name' => 'thr_'.$u,
            'db_username' => 'thr_'.substr($u, -8),
            'status' => 'active',
            'settings' => [],
        ]);
        $tenant->db_password = 'irrelevant';
        $tenant->save();

        return $tenant;
    }
}
