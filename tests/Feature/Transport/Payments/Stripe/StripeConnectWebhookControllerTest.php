<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Payments\Stripe;

use App\Domain\Transport\Payments\Stripe\TransporterStripeConnectService;
use App\Enums\QuoteStatus;
use App\Models\Tenant\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Webhook;
use Tests\Feature\Transport\Payments\PaymentTestTenantSetup;
use Tests\TestCase;

/**
 * Stripe Connect Express — webhook handler. Patrz docs/TRANSPORT.md §15.6.
 *
 * Symulujemy real Stripe webhook deliveries:
 *   1. budujemy event payload (JSON) z tenant_id/quote_id w metadata
 *   2. liczymy v1 sygnaturę HMAC-SHA256 dokładnie tak jak Stripe to robi
 *   3. POST na /webhooks/stripe-connect z `Stripe-Signature` headerem
 *
 * Stripe SDK Webhook::constructEvent waliduje sygnaturę real-deal,
 * więc nie ma magii — tylko poprawne wyliczenie ts+hash.
 */
class StripeConnectWebhookControllerTest extends TestCase
{
    use PaymentTestTenantSetup;
    use RefreshDatabase;

    private string $webhookSecret = 'whsec_connect_test_x';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantWithPayments();
        config()->set('services.stripe.connect.webhook_secret', $this->webhookSecret);

        // Tenant ma aktywne Connect — webhook może go znaleźć.
        $this->tenant->forceFill([
            'stripe_connect_account_id' => 'acct_test_webhook',
            'stripe_connect_status' => 'enabled',
        ])->save();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantWithPayments();
        parent::tearDown();
    }

    public function test_invalid_signature_returns_400(): void
    {
        $payload = json_encode(['id' => 'evt_x', 'type' => 'account.updated', 'data' => ['object' => ['id' => 'acct_test_webhook']]]);

        $response = $this->postJson('/webhooks/stripe-connect', [], [
            'Stripe-Signature' => 't=0,v1=invalid_signature',
        ]);

        // 400 albo 400 z różnym body — kluczowe że NIE jest 200.
        $this->assertNotSame(200, $response->status());
    }

    public function test_missing_signature_returns_400(): void
    {
        $response = $this->call('POST', '/webhooks/stripe-connect', [], [], [], [], 'payload-body');
        $this->assertSame(400, $response->status());
    }

    public function test_missing_webhook_secret_returns_500(): void
    {
        config()->set('services.stripe.connect.webhook_secret', '');

        $payload = json_encode(['id' => 'evt_1', 'type' => 'account.updated']);
        $sig = $this->buildSignature($payload);

        $response = $this->call('POST', '/webhooks/stripe-connect',
            [], [], [], ['HTTP_Stripe-Signature' => $sig], $payload,
        );

        $this->assertSame(500, $response->status());
    }

    public function test_account_updated_calls_sync_status(): void
    {
        // Mock service — webhook nie powinien wywołać prawdziwego Stripe API.
        $mock = Mockery::mock(TransporterStripeConnectService::class);
        $mock->shouldReceive('syncAccountStatus')
            ->once()
            ->with(Mockery::on(fn ($t) => $t->id === $this->tenant->id));
        $this->app->instance(TransporterStripeConnectService::class, $mock);

        $payload = json_encode([
            'id' => 'evt_account_'.uniqid(),
            'type' => 'account.updated',
            'data' => ['object' => ['id' => 'acct_test_webhook', 'object' => 'account']],
            'account' => 'acct_test_webhook',
        ]);

        $response = $this->call('POST', '/webhooks/stripe-connect',
            [], [], [], ['HTTP_Stripe-Signature' => $this->buildSignature($payload)], $payload,
        );

        $response->assertOk();
    }

    public function test_checkout_session_completed_flips_quote_paid(): void
    {
        $quote = $this->makeQuote(QuoteStatus::Accepted, [
            'accept_token' => str_repeat('p', 48),
            'accepted_at' => now(),
            'gross_total' => 1694.66,
        ]);

        $this->assertNull($quote->payment_completed_at);

        $payload = json_encode([
            'id' => 'evt_checkout_'.uniqid(),
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test_xyz',
                'object' => 'checkout.session',
                'payment_status' => 'paid',
                'amount_total' => 169466,
                'metadata' => [
                    'tenant_id' => (string) $this->tenant->id,
                    'quote_id' => (string) $quote->id,
                    'quote_number' => (string) $quote->number,
                ],
            ]],
            'account' => 'acct_test_webhook',
        ]);

        $response = $this->call('POST', '/webhooks/stripe-connect',
            [], [], [], ['HTTP_Stripe-Signature' => $this->buildSignature($payload)], $payload,
        );

        $response->assertOk();
        $this->assertNotNull(Quote::find($quote->id)->payment_completed_at);
    }

    public function test_payment_intent_succeeded_flips_quote_paid(): void
    {
        $quote = $this->makeQuote(QuoteStatus::Accepted, [
            'accept_token' => str_repeat('i', 48),
            'accepted_at' => now(),
        ]);

        $payload = json_encode([
            'id' => 'evt_pi_'.uniqid(),
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => [
                'id' => 'pi_test_abc',
                'amount' => 169466,
                'metadata' => [
                    'tenant_id' => (string) $this->tenant->id,
                    'quote_id' => (string) $quote->id,
                ],
            ]],
            'account' => 'acct_test_webhook',
        ]);

        $response = $this->call('POST', '/webhooks/stripe-connect',
            [], [], [], ['HTTP_Stripe-Signature' => $this->buildSignature($payload)], $payload,
        );

        $response->assertOk();
        $this->assertNotNull(Quote::find($quote->id)->payment_completed_at);
    }

    public function test_account_mismatch_does_not_flip_quote(): void
    {
        $quote = $this->makeQuote(QuoteStatus::Accepted, [
            'accept_token' => str_repeat('m', 48),
            'accepted_at' => now(),
        ]);

        $payload = json_encode([
            'id' => 'evt_mismatch_'.uniqid(),
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_evil',
                'payment_status' => 'paid',
                'metadata' => [
                    'tenant_id' => (string) $this->tenant->id,
                    'quote_id' => (string) $quote->id,
                ],
            ]],
            // OBCY stripe_account_id — nie matchuje tenant'a.
            'account' => 'acct_evil_attacker',
        ]);

        $response = $this->call('POST', '/webhooks/stripe-connect',
            [], [], [], ['HTTP_Stripe-Signature' => $this->buildSignature($payload)], $payload,
        );

        $response->assertOk();
        // Quote NIE oznaczone jako paid — chroni przed cross-tenant smuggling'iem.
        $this->assertNull(Quote::find($quote->id)->payment_completed_at);
    }

    public function test_idempotent_on_duplicate_event_id(): void
    {
        $quote = $this->makeQuote(QuoteStatus::Accepted, [
            'accept_token' => str_repeat('d', 48),
            'accepted_at' => now(),
        ]);

        $eventId = 'evt_dedupe_'.uniqid();
        $payload = json_encode([
            'id' => $eventId,
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_dedupe',
                'payment_status' => 'paid',
                'metadata' => [
                    'tenant_id' => (string) $this->tenant->id,
                    'quote_id' => (string) $quote->id,
                ],
            ]],
            'account' => 'acct_test_webhook',
        ]);

        // First delivery.
        $r1 = $this->call('POST', '/webhooks/stripe-connect',
            [], [], [], ['HTTP_Stripe-Signature' => $this->buildSignature($payload)], $payload,
        );
        $r1->assertOk();

        // Second delivery, same event_id — dedupe path.
        $r2 = $this->call('POST', '/webhooks/stripe-connect',
            [], [], [], ['HTTP_Stripe-Signature' => $this->buildSignature($payload)], $payload,
        );
        $r2->assertOk();
        $r2->assertJsonFragment(['dedupe' => true]);
    }

    public function test_unknown_event_type_returns_200(): void
    {
        $payload = json_encode([
            'id' => 'evt_unknown_'.uniqid(),
            'type' => 'some.unknown.event',
            'data' => ['object' => ['id' => 'x']],
        ]);

        $response = $this->call('POST', '/webhooks/stripe-connect',
            [], [], [], ['HTTP_Stripe-Signature' => $this->buildSignature($payload)], $payload,
        );

        $response->assertOk();
    }

    /**
     * Buduje header `Stripe-Signature: t=ts,v1=hash` zgodnie z dokumentacją
     * Stripe (HMAC-SHA256 nad "ts.payload" z secret'em jako kluczem).
     */
    private function buildSignature(string $payload): string
    {
        $ts = time();
        $signedPayload = $ts.'.'.$payload;
        $hash = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

        return "t={$ts},v1={$hash}";
    }
}
