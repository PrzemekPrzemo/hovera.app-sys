<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Payments;

use App\Enums\QuoteStatus;
use App\Models\Tenant\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Direct-charge payments MVP — patrz docs/TRANSPORT.md §13.
 *
 * Persistence + rendering kolumn `payment_url`, `payment_method_label`,
 * `payment_notes` na Quote'cie i na landing'u publicznym.
 */
class QuotePaymentUrlTest extends TestCase
{
    use PaymentTestTenantSetup;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantWithPayments();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantWithPayments();
        parent::tearDown();
    }

    public function test_payment_url_persists_on_quote(): void
    {
        $quote = $this->makeQuote(QuoteStatus::Accepted, [
            'accept_token' => str_repeat('a', 48),
            'accepted_at' => now(),
            'payment_url' => 'https://buy.stripe.com/test_abc123',
            'payment_method_label' => 'Stripe',
            'payment_notes' => 'Zaliczka 30% do piątku.',
        ]);

        $fresh = Quote::find($quote->id);
        $this->assertSame('https://buy.stripe.com/test_abc123', $fresh->payment_url);
        $this->assertSame('Stripe', $fresh->payment_method_label);
        $this->assertSame('Zaliczka 30% do piątku.', $fresh->payment_notes);
        $this->assertNull($fresh->payment_completed_at);
    }

    public function test_landing_renders_pay_button_when_payment_url_set(): void
    {
        $token = str_repeat('b', 48);
        $this->makeQuote(QuoteStatus::Accepted, [
            'accept_token' => $token,
            'accepted_at' => now(),
            'payment_url' => 'https://buy.stripe.com/test_abc123',
            'payment_method_label' => 'Stripe',
        ]);

        $response = $this->get("/transport/quote/{$this->tenant->slug}/{$token}");
        $response->assertOk();
        $response->assertSee('https://buy.stripe.com/test_abc123', escape: false);
        $response->assertSee('Stripe');
        $response->assertSee('Zapłać teraz', escape: false);
        $response->assertSee('data-testid="payment-pay-btn"', escape: false);
        // Disclaimer must always appear when payment block is shown.
        $response->assertSee('data-testid="payment-disclaimer"', escape: false);
    }

    public function test_payment_section_hidden_when_quote_not_accepted(): void
    {
        $token = str_repeat('c', 48);
        $this->makeQuote(QuoteStatus::Sent, [
            'accept_token' => $token,
            'payment_url' => 'https://buy.stripe.com/test_xyz',
        ]);

        $response = $this->get("/transport/quote/{$this->tenant->slug}/{$token}");
        $response->assertOk();
        // Payment UI gated on accepted status — sent quote shows accept buttons only.
        $response->assertDontSee('data-testid="payment-section"', escape: false);
    }
}
