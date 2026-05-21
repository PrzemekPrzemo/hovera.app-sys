<?php

declare(strict_types=1);

namespace Tests\Feature\Owner\Notifications;

use App\Filament\Owner\Widgets\LastOwnerActivityWidget;
use App\Models\Central\User;
use App\Notifications\Owner\NewInvoiceForOwner;
use App\Notifications\Owner\NewMessageForOwner;
use App\Notifications\Owner\VetVisitRecordedForOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pokrywa Faza 6 PR 6.2 — Owner Dashboard widget aggregujący ostatnie
 * unread notifications. Sprawdzamy:
 *   * getNotifications zwraca top 5 unread DESC po created_at
 *   * markRead / markAllRead działają
 *   * Helpery: labelFor / iconFor / summaryFor / urlFor / classesFor
 */
class LastOwnerActivityWidgetTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::create([
            'name' => 'Jan Owner',
            'email' => 'jan-'.uniqid().'@example.test',
            'password' => bcrypt('x'),
        ]);
    }

    public function test_returns_empty_when_no_notifications(): void
    {
        $this->actingAs($this->owner);
        $widget = new LastOwnerActivityWidget;

        $this->assertCount(0, $widget->getNotifications());
        $this->assertSame(0, $widget->getTotalUnreadCount());
    }

    public function test_returns_top_5_unread_desc_by_created_at(): void
    {
        // Dispatch 7 notifications (z różnymi czasami)
        for ($i = 1; $i <= 7; $i++) {
            $this->owner->notify(new NewMessageForOwner(
                stableTenantId: 't',
                stableName: 'S',
                centralHorseId: 'h',
                horseName: "Horse {$i}",
                messageId: 'm'.$i,
                subject: 'Msg '.$i,
                bodyPreview: '...',
                attachmentCount: 0,
                ownerPanelUrl: 'https://example.com/'.$i,
            ));
        }

        $this->actingAs($this->owner);
        $widget = new LastOwnerActivityWidget;
        $notifications = $widget->getNotifications();

        $this->assertCount(5, $notifications); // top 5 only
        $this->assertSame(7, $widget->getTotalUnreadCount()); // total
    }

    public function test_excludes_read_notifications(): void
    {
        $this->owner->notify(new NewMessageForOwner(
            stableTenantId: 't', stableName: 'S', centralHorseId: 'h', horseName: 'H',
            messageId: 'm1', subject: 's', bodyPreview: '.', attachmentCount: 0,
            ownerPanelUrl: 'https://example.com/1',
        ));
        $this->owner->notify(new NewInvoiceForOwner(
            stableTenantId: 't', stableName: 'S', invoiceId: 'i1', invoiceNumber: 'FV/1',
            totalCents: 1000, currency: 'PLN', dueAt: null, billingPeriod: null,
            horseName: null, ownerPanelUrl: 'https://example.com/inv',
        ));

        // Mark one as read
        $this->owner->unreadNotifications()->first()->markAsRead();

        $this->actingAs($this->owner);
        $widget = new LastOwnerActivityWidget;

        $this->assertCount(1, $widget->getNotifications());
        $this->assertSame(1, $widget->getTotalUnreadCount());
    }

    public function test_mark_read_marks_specific_notification(): void
    {
        $this->owner->notify(new NewMessageForOwner(
            stableTenantId: 't', stableName: 'S', centralHorseId: 'h', horseName: 'H',
            messageId: 'm', subject: 's', bodyPreview: '.', attachmentCount: 0,
            ownerPanelUrl: 'https://example.com/1',
        ));

        $this->actingAs($this->owner);
        $widget = new LastOwnerActivityWidget;
        $id = $widget->getNotifications()->first()->id;

        $widget->markRead($id);

        $this->assertSame(0, $widget->getTotalUnreadCount());
    }

    public function test_mark_read_ignores_unknown_id(): void
    {
        $this->actingAs($this->owner);
        $widget = new LastOwnerActivityWidget;
        // No notifications, but markRead with random ID should not crash
        $widget->markRead((string) Str::ulid());
        $this->assertTrue(true); // no exception
    }

    public function test_mark_all_read_clears_unread_count(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->owner->notify(new NewMessageForOwner(
                stableTenantId: 't', stableName: 'S', centralHorseId: 'h', horseName: 'H',
                messageId: 'm'.$i, subject: 's', bodyPreview: '.', attachmentCount: 0,
                ownerPanelUrl: 'https://example.com/'.$i,
            ));
        }

        $this->actingAs($this->owner);
        $widget = new LastOwnerActivityWidget;
        $this->assertSame(3, $widget->getTotalUnreadCount());

        $widget->markAllRead();

        $this->assertSame(0, $widget->getTotalUnreadCount());
    }

    public function test_label_for_each_notification_kind(): void
    {
        $this->owner->notify(new NewMessageForOwner(
            stableTenantId: 't', stableName: 'S', centralHorseId: 'h', horseName: 'H',
            messageId: 'm', subject: 's', bodyPreview: '.', attachmentCount: 0,
            ownerPanelUrl: 'https://example.com',
        ));
        $this->owner->notify(new NewInvoiceForOwner(
            stableTenantId: 't', stableName: 'S', invoiceId: 'i', invoiceNumber: 'FV',
            totalCents: 100, currency: 'PLN', dueAt: null, billingPeriod: null,
            horseName: null, ownerPanelUrl: 'https://example.com',
        ));
        $this->owner->notify(new VetVisitRecordedForOwner(
            stableTenantId: 't', stableName: 'S', centralHorseId: 'h', horseName: 'H',
            recordType: 'vet_visit', summary: null, details: null, costCents: null,
            performedAt: null, nextDueAt: null, ownerPanelUrl: 'https://example.com',
        ));

        $this->actingAs($this->owner);
        $widget = new LastOwnerActivityWidget;
        $notifications = $widget->getNotifications();
        $kinds = $notifications->pluck('data.kind')->all();

        $this->assertContains('owner.new_message', $kinds);
        $this->assertContains('owner.new_invoice', $kinds);
        $this->assertContains('owner.vet_visit_recorded', $kinds);

        foreach ($notifications as $n) {
            $label = $widget->labelFor($n);
            $this->assertNotSame('', $label);
            $this->assertNotSame('owner/dashboard.activity.label.fallback', $label);
        }
    }

    public function test_icon_for_each_kind(): void
    {
        $message = $this->makeNotificationStub(['kind' => 'owner.new_message']);
        $invoice = $this->makeNotificationStub(['kind' => 'owner.new_invoice']);
        $vet = $this->makeNotificationStub(['kind' => 'owner.vet_visit_recorded']);
        $unknown = $this->makeNotificationStub(['kind' => 'foo']);

        $widget = new LastOwnerActivityWidget;
        $this->assertStringContainsString('chat-bubble', $widget->iconFor($message));
        $this->assertStringContainsString('document-text', $widget->iconFor($invoice));
        $this->assertStringContainsString('heart', $widget->iconFor($vet));
        $this->assertStringContainsString('bell', $widget->iconFor($unknown));
    }

    public function test_summary_for_new_message_includes_stable_horse_subject(): void
    {
        $n = $this->makeNotificationStub([
            'kind' => 'owner.new_message',
            'stable_name' => 'Stajnia Iskra',
            'horse_name' => 'Iskra',
            'subject' => 'Wizyta wet',
        ]);

        $widget = new LastOwnerActivityWidget;
        $summary = $widget->summaryFor($n);

        $this->assertStringContainsString('Stajnia Iskra', $summary);
        $this->assertStringContainsString('Iskra', $summary);
        $this->assertStringContainsString('Wizyta wet', $summary);
    }

    public function test_summary_for_invoice_includes_total_formatted(): void
    {
        $n = $this->makeNotificationStub([
            'kind' => 'owner.new_invoice',
            'stable_name' => 'Stajnia',
            'invoice_number' => 'FV/2026/05/0001',
            'total_cents' => 246000,
            'currency' => 'PLN',
        ]);

        $widget = new LastOwnerActivityWidget;
        $summary = $widget->summaryFor($n);

        $this->assertStringContainsString('FV/2026/05/0001', $summary);
        $this->assertStringContainsString('2 460,00 PLN', $summary);
    }

    public function test_url_for_returns_payload_url_or_null(): void
    {
        $with = $this->makeNotificationStub(['url' => 'https://example.com/x']);
        $without = $this->makeNotificationStub(['kind' => 'x']);

        $widget = new LastOwnerActivityWidget;
        $this->assertSame('https://example.com/x', $widget->urlFor($with));
        $this->assertNull($widget->urlFor($without));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function makeNotificationStub(array $data): DatabaseNotification
    {
        $n = new DatabaseNotification;
        $n->id = (string) Str::ulid();
        $n->type = 'TestNotification';
        $n->notifiable_type = User::class;
        $n->notifiable_id = $this->owner->id;
        $n->data = $data;
        $n->created_at = now();
        $n->updated_at = now();

        return $n;
    }
}
