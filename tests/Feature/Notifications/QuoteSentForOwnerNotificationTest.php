<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Domain\Notifications\Owner\OwnerNotificationDispatcher;
use App\Models\Central\User;
use App\Notifications\Owner\QuoteSentForOwnerNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

/**
 * Owner quote-sent notification — dispatch przez nowy helper
 * `OwnerNotificationDispatcher::forCentralUser()`.
 *
 * Sprawdza:
 *  - forCentralUser z null/empty → silent skip (brak crash'a)
 *  - forCentralUser z valid id → notify user via database + mail channels
 *  - Notification toDatabase payload zawiera kluczowe pola (kind, url, ceny)
 */
class QuoteSentForOwnerNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatcher_skips_silently_when_user_id_null(): void
    {
        NotificationFacade::fake();

        app(OwnerNotificationDispatcher::class)->forCentralUser(null, $this->makeNotification());

        NotificationFacade::assertNothingSent();
    }

    public function test_dispatcher_skips_silently_when_user_id_empty(): void
    {
        NotificationFacade::fake();

        app(OwnerNotificationDispatcher::class)->forCentralUser('', $this->makeNotification());

        NotificationFacade::assertNothingSent();
    }

    public function test_dispatcher_notifies_central_user_when_id_resolves(): void
    {
        NotificationFacade::fake();

        $owner = User::create([
            'email' => 'owner-'.uniqid().'@example.com',
            'name' => 'Marek',
            'password' => bcrypt('secret'),
        ]);

        app(OwnerNotificationDispatcher::class)
            ->forCentralUser($owner->id, $this->makeNotification());

        NotificationFacade::assertSentTo($owner, QuoteSentForOwnerNotification::class);
    }

    public function test_database_payload_contains_key_fields(): void
    {
        $n = $this->makeNotification();
        $payload = $n->toDatabase(new \stdClass);

        $this->assertSame('owner.quote_sent', $payload['kind']);
        $this->assertSame('Marek Trans', $payload['transporter_name']);
        $this->assertSame('OF/2026/05/001', $payload['quote_number']);
        $this->assertSame(123_456, $payload['price_gross_cents']);
        $this->assertSame('PLN', $payload['currency']);
        // Notification payload preferuje orderPanelUrl (in-app klick z bell
        // → /owner/transport-orders gdzie ma liste ofert z PR #395),
        // fallback do publicLandingUrl gdy panel URL null.
        $this->assertSame('https://app.hovera.app/owner/transport-orders', $payload['url']);
    }

    private function makeNotification(): QuoteSentForOwnerNotification
    {
        return new QuoteSentForOwnerNotification(
            transporterTenantId: 'tenant-id',
            transporterName: 'Marek Trans',
            quoteNumber: 'OF/2026/05/001',
            priceGrossCents: 123_456,
            currency: 'PLN',
            proposedDate: '2026-06-01',
            pickupAddress: 'Warszawa',
            dropoffAddress: 'Kraków',
            publicLandingUrl: 'https://app.hovera.app/transport/quote/marek/'.str_repeat('a', 48),
            orderPanelUrl: 'https://app.hovera.app/owner/transport-orders',
        );
    }
}
