<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Payments;

use App\Enums\QuoteStatus;
use App\Models\Tenant\TransportSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cztery stany sekcji płatności na quote landing'u:
 *   1. payment_completed_at → green confirmation
 *   2. payment_url → "Zapłać teraz" CTA
 *   3. payment_instructions (z settings) → fallback bank-transfer info
 *   4. nic z powyższych → "skontaktuj się z transporterem"
 *
 * Disclaimer "Hovera NIE pośredniczy w płatnościach" jest ZAWSZE widoczny
 * w sekcji płatności. Patrz docs/TRANSPORT.md §13.
 */
class QuoteLandingPaymentDisplayTest extends TestCase
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

    public function test_state_1_completed_shows_green_confirmation(): void
    {
        $token = str_repeat('a', 48);
        $this->makeQuote(QuoteStatus::Accepted, [
            'accept_token' => $token,
            'accepted_at' => now()->subDay(),
            'payment_url' => 'https://buy.stripe.com/test_x',
            'payment_completed_at' => now()->subHours(2),
        ]);

        $response = $this->get("/transport/quote/{$this->tenant->slug}/{$token}");
        $response->assertOk();
        $response->assertSee('data-testid="payment-confirmed"', escape: false);
        $response->assertSee('Płatność potwierdzona przez przewoźnika', escape: false);
        // Disclaimer always present.
        $response->assertSee('data-testid="payment-disclaimer"', escape: false);
        $response->assertSee('NIE przyjmuje płatności', escape: false);
        // Pay button must NOT appear once payment is completed.
        $response->assertDontSee('data-testid="payment-pay-btn"', escape: false);
    }

    public function test_state_2_payment_url_shows_pay_button(): void
    {
        $token = str_repeat('b', 48);
        $this->makeQuote(QuoteStatus::Accepted, [
            'accept_token' => $token,
            'accepted_at' => now(),
            'payment_url' => 'https://secure.przelewy24.pl/abc',
            'payment_method_label' => 'Przelewy24',
        ]);

        $response = $this->get("/transport/quote/{$this->tenant->slug}/{$token}");
        $response->assertOk();
        $response->assertSee('data-testid="payment-pay-btn"', escape: false);
        $response->assertSee('https://secure.przelewy24.pl/abc', escape: false);
        $response->assertSee('Przelewy24');
        // External link best-practice attributes.
        $response->assertSee('target="_blank"', escape: false);
        $response->assertSee('rel="noopener noreferrer nofollow"', escape: false);
        // Disclaimer always present.
        $response->assertSee('data-testid="payment-disclaimer"', escape: false);
    }

    public function test_state_3_payment_instructions_fallback_when_no_url(): void
    {
        // Settings ma instrukcje, quote nie ma payment_url.
        $settings = TransportSettings::current();
        $settings->forceFill([
            'payment_instructions' => "Bank ABC\n12 3456 7890 1234 5678 9012 3456\nW tytule: numer oferty",
        ])->save();

        $token = str_repeat('c', 48);
        $this->makeQuote(QuoteStatus::Accepted, [
            'accept_token' => $token,
            'accepted_at' => now(),
            'payment_url' => null,
        ]);

        $response = $this->get("/transport/quote/{$this->tenant->slug}/{$token}");
        $response->assertOk();
        $response->assertSee('data-testid="payment-instructions"', escape: false);
        $response->assertSee('Bank ABC', escape: false);
        $response->assertSee('12 3456 7890', escape: false);
        $response->assertSee('Instrukcje płatności', escape: false);
        // Disclaimer always present.
        $response->assertSee('data-testid="payment-disclaimer"', escape: false);
        // No pay button.
        $response->assertDontSee('data-testid="payment-pay-btn"', escape: false);
    }

    public function test_state_4_nothing_shows_contact_transporter(): void
    {
        $token = str_repeat('d', 48);
        $this->makeQuote(QuoteStatus::Accepted, [
            'accept_token' => $token,
            'accepted_at' => now(),
            'payment_url' => null,
        ]);
        // TransportSettings::current() will autocreate with all payment fields null.

        $response = $this->get("/transport/quote/{$this->tenant->slug}/{$token}");
        $response->assertOk();
        $response->assertSee('data-testid="payment-contact"', escape: false);
        $response->assertSee('Skontaktuj się', escape: false);
        // Transporter contact details from tenant branding.
        $response->assertSee('biuro@firma.test');
        // Disclaimer always present.
        $response->assertSee('data-testid="payment-disclaimer"', escape: false);
    }
}
