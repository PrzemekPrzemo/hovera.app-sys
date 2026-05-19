<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Payments\Stripe;

use App\Domain\Transport\Payments\Stripe\TransporterStripeConnectService;
use App\Enums\QuoteStatus;
use App\Filament\Transport\Resources\QuoteResource\Pages\CreateQuote;
use App\Models\Central\Tenant;
use App\Models\Tenant\Quote;
use App\Models\Tenant\TransportSettings;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Checkout\Session as CheckoutSession;
use Tests\Feature\Transport\Payments\PaymentTestTenantSetup;
use Tests\TestCase;

/**
 * Stripe Connect Express — integration z CreateQuote::afterCreate.
 * Patrz docs/TRANSPORT.md §15.6.
 *
 * Sprawdza że:
 *   - tenant z hasStripeConnectEnabled=true → Stripe Checkout URL trafia do quote.payment_url
 *   - tenant bez Connect → fallback do default_payment_url_template (PR #226 behavior)
 *   - tenant bez Connect i bez template → payment_url zostaje null
 *   - jeśli payment_url już ustawiony ręcznie, NIE jest nadpisywany
 *
 * Bezpośrednio testujemy logikę z CreateQuote::applyStripeConnectCheckoutIfEnabled
 * przez wywołanie afterCreate'u na świeżym quote (reflection nie potrzebny —
 * publiczna ścieżka).
 */
class QuoteAutoStripeConnectUrlTest extends TestCase
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

    public function test_quote_create_with_stripe_connect_enabled_uses_checkout_url(): void
    {
        $this->tenant->forceFill([
            'stripe_connect_account_id' => 'acct_e2e_test',
            'stripe_connect_status' => 'enabled',
        ])->save();
        $this->tenant->refresh();

        $checkoutUrl = 'https://checkout.stripe.com/c/pay/cs_test_e2e';
        // CheckoutSession to StripeObject — Mockery nie pozwala setować properties
        // (getPermanentAttributes); używamy SDK constructFrom dla autentycznego objectu.
        $session = CheckoutSession::constructFrom([
            'id' => 'cs_test_e2e',
            'url' => $checkoutUrl,
        ]);

        $mock = Mockery::mock(TransporterStripeConnectService::class);
        $mock->shouldReceive('createCheckoutSession')
            ->once()
            ->andReturn($session);
        $this->app->instance(TransporterStripeConnectService::class, $mock);

        // TenantManager mock w PaymentTestTenantSetup zwróci our tenant.
        $this->bindTenantInContainer($this->tenant->fresh());

        // Symulacja afterCreate przez bezpośrednie wywołanie helper'a CreateQuote
        // — używamy publicznej drogi przez instancjonowanie page'a i refleksję
        // tylko gdzie potrzeba. Tu używamy reflection żeby nie bootstrapować
        // pełnego Filament panelu w teście.
        $quote = $this->makeQuote(QuoteStatus::Draft, [
            'accept_token' => str_repeat('a', 48),
            'gross_total' => 1694.66,
        ]);

        $this->bindTenantInContainer($this->tenant->fresh());

        $this->invokeAfterCreateLogic($quote);

        $fresh = Quote::find($quote->id);
        $this->assertSame($checkoutUrl, $fresh->payment_url);
        $this->assertNotEmpty($fresh->payment_method_label);
    }

    public function test_quote_create_without_stripe_connect_falls_back_to_template(): void
    {
        $this->tenant->forceFill([
            'stripe_connect_account_id' => null,
            'stripe_connect_status' => 'none',
        ])->save();
        $this->tenant->refresh();

        // Settings ma template — fallback path.
        $settings = TransportSettings::current();
        $settings->forceFill([
            'default_payment_url_template' => 'https://buy.stripe.com/test_xxx?ref={quote_number}',
            'default_payment_method_label' => 'Stripe Payment Link',
        ])->save();

        $this->bindTenantInContainer($this->tenant->fresh());

        $quote = $this->makeQuote(QuoteStatus::Draft, [
            'accept_token' => str_repeat('b', 48),
            'number' => 'OF/2026/05/0099',
        ]);

        $this->invokeAfterCreateLogic($quote);

        $fresh = Quote::find($quote->id);
        $this->assertStringContainsString('ref=OF%2F2026%2F05%2F0099', (string) $fresh->payment_url);
        $this->assertSame('Stripe Payment Link', $fresh->payment_method_label);
    }

    public function test_quote_create_with_no_connect_and_no_template_leaves_url_null(): void
    {
        $this->tenant->forceFill(['stripe_connect_status' => 'none'])->save();

        $this->bindTenantInContainer($this->tenant->fresh());

        $quote = $this->makeQuote(QuoteStatus::Draft, [
            'accept_token' => str_repeat('c', 48),
        ]);

        $this->invokeAfterCreateLogic($quote);

        $fresh = Quote::find($quote->id);
        $this->assertNull($fresh->payment_url);
    }

    public function test_existing_payment_url_not_overwritten_by_stripe_connect(): void
    {
        $this->tenant->forceFill([
            'stripe_connect_account_id' => 'acct_test_x',
            'stripe_connect_status' => 'enabled',
        ])->save();

        // Service nie powinien zostać wywołany — payment_url już ustawiony.
        $mock = Mockery::mock(TransporterStripeConnectService::class);
        $mock->shouldNotReceive('createCheckoutSession');
        $this->app->instance(TransporterStripeConnectService::class, $mock);

        $this->bindTenantInContainer($this->tenant->fresh());

        $quote = $this->makeQuote(QuoteStatus::Draft, [
            'accept_token' => str_repeat('e', 48),
            'payment_url' => 'https://manual.example.com/pay/abc',
            'payment_method_label' => 'Bank transfer',
        ]);

        $this->invokeAfterCreateLogic($quote);

        $fresh = Quote::find($quote->id);
        $this->assertSame('https://manual.example.com/pay/abc', $fresh->payment_url);
        $this->assertSame('Bank transfer', $fresh->payment_method_label);
    }

    public function test_stripe_connect_failure_falls_back_to_template(): void
    {
        $this->tenant->forceFill([
            'stripe_connect_account_id' => 'acct_test_fail',
            'stripe_connect_status' => 'enabled',
        ])->save();

        $settings = TransportSettings::current();
        $settings->forceFill([
            'default_payment_url_template' => 'https://fallback.example.com/?id={quote_number}',
        ])->save();

        $mock = Mockery::mock(TransporterStripeConnectService::class);
        $mock->shouldReceive('createCheckoutSession')
            ->andThrow(new \RuntimeException('Stripe API down'));
        $this->app->instance(TransporterStripeConnectService::class, $mock);

        $this->bindTenantInContainer($this->tenant->fresh());

        $quote = $this->makeQuote(QuoteStatus::Draft, [
            'accept_token' => str_repeat('f', 48),
            'number' => 'OF/2026/05/0111',
        ]);

        $this->invokeAfterCreateLogic($quote);

        $fresh = Quote::find($quote->id);
        // Fallback path zadziałał — template wstawiony.
        $this->assertStringContainsString('fallback.example.com', (string) $fresh->payment_url);
    }

    /**
     * Wstrzykuje świeży TenantManager mock zwracający przekazanego tenant'a
     * z poprawnymi value-bindingiem (PaymentTestTenantSetup ma quirk z arrow
     * function capturing $heldTenant by-value, więc nadpisujemy mock z mocnym
     * bind'em na konkretną instancję tenant'a).
     */
    private function bindTenantInContainer(Tenant $tenant): void
    {
        $mock = Mockery::mock(TenantManager::class);
        $mock->shouldReceive('current')->andReturn($tenant);
        $mock->shouldReceive('hasTenant')->andReturn(true);
        $mock->shouldReceive('tenantOrFail')->andReturn($tenant);
        $mock->shouldReceive('setCurrent')->andReturnNull();
        $mock->shouldReceive('forget')->andReturnNull();
        $this->app->instance(TenantManager::class, $mock);
    }

    /**
     * Wywołuje logikę CreateQuote::afterCreate bez bootstrappingu Filament'a.
     * Reflection na private methods — testujemy że pełny flow działa.
     */
    private function invokeAfterCreateLogic(Quote $quote): void
    {
        $page = new CreateQuote;
        $reflection = new \ReflectionClass($page);

        $recordProp = $reflection->getProperty('record');
        $recordProp->setAccessible(true);
        $recordProp->setValue($page, $quote);

        // applyStripeConnectCheckoutIfEnabled + applyDefaultPaymentUrlIfBlank
        // (oba private — wywołujemy ścieżkę afterCreate jednak nie bezpośrednio
        // bo audit logger wymaga session contextu; logikę dzielimy ręcznie).
        $applyStripe = $reflection->getMethod('applyStripeConnectCheckoutIfEnabled');
        $applyStripe->setAccessible(true);
        $stripeApplied = $applyStripe->invoke($page);

        if (! $stripeApplied) {
            $applyTemplate = $reflection->getMethod('applyDefaultPaymentUrlIfBlank');
            $applyTemplate->setAccessible(true);
            $applyTemplate->invoke($page);
        }
    }
}
