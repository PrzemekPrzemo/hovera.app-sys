<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Payments;

use App\Domain\Transport\Payments\PaymentUrlTemplate;
use App\Enums\QuoteStatus;
use App\Models\Tenant\TransportSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Patrz docs/TRANSPORT.md §13.
 *
 * Testy logiki rozwijania placeholderów w template'cie URL'a płatności.
 * Sam Filament-level auto-fill ciężko testować bez bootstrapowania panelu —
 * tutaj sprawdzamy core'ową logikę PaymentUrlTemplate, którą wywołuje
 * CreateQuote::afterCreate().
 */
class PaymentDefaultsFromSettingsTest extends TestCase
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

    public function test_template_expands_quote_number_placeholder(): void
    {
        $quote = $this->makeQuote(QuoteStatus::Draft, ['number' => 'OF/2026/05/0042']);
        $expanded = PaymentUrlTemplate::expand(
            'https://example.com/pay?ref={quote_number}',
            $quote,
        );

        $this->assertSame('https://example.com/pay?ref=OF%2F2026%2F05%2F0042', $expanded);
    }

    public function test_template_expands_gross_total_and_customer_name(): void
    {
        $quote = $this->makeQuote(QuoteStatus::Draft, [
            'gross_total' => 1694.66,
            'customer_name' => 'Jan Kowalski',
        ]);
        $expanded = PaymentUrlTemplate::expand(
            'https://example.com/pay?amount={gross_total_pln}&name={customer_name}',
            $quote,
        );

        $this->assertStringContainsString('amount=1694.66', $expanded);
        $this->assertStringContainsString('name=Jan%20Kowalski', $expanded);
    }

    public function test_settings_persist_default_payment_template_and_label(): void
    {
        $settings = TransportSettings::current();
        $settings->forceFill([
            'default_payment_url_template' => 'https://buy.stripe.com/x?ref={quote_number}',
            'default_payment_method_label' => 'Stripe',
            'payment_instructions' => 'Bank XYZ, 12 3456 ...',
        ])->save();

        $fresh = TransportSettings::current();
        $this->assertSame('https://buy.stripe.com/x?ref={quote_number}', $fresh->default_payment_url_template);
        $this->assertSame('Stripe', $fresh->default_payment_method_label);
        $this->assertStringContainsString('Bank XYZ', $fresh->payment_instructions);
    }

    public function test_template_leaves_unknown_placeholders_intact(): void
    {
        $quote = $this->makeQuote(QuoteStatus::Draft);
        $expanded = PaymentUrlTemplate::expand(
            'https://example.com/pay?ref={quote_number}&unknown={foo_bar}',
            $quote,
        );

        // Unknown placeholders pass through — transporter can use any URL.
        $this->assertStringContainsString('unknown={foo_bar}', $expanded);
    }
}
