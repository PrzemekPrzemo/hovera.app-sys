<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Payments;

use App\Enums\QuoteStatus;
use App\Models\Tenant\TransportSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Patrz docs/TRANSPORT.md §13 + §1.1 (pozycjonowanie marketplace).
 *
 * Disclaimer "Hovera NIE pośredniczy w płatnościach / NIE przyjmuje płatności"
 * MUSI być widoczny zawsze gdy renderujemy cokolwiek związanego z płatnościami.
 * To wymóg pozycjonowania marketplace (jesteśmy intermediary, nie merchantem)
 * i ma znaczenie prawne — żeby klient wiedział, gdzie kierować reklamacje
 * płatności (nie do Hovery).
 *
 * Ten test pilnuje, żeby ktoś przypadkiem nie zhide'ował disclaimera
 * w żadnym z 4 stanów sekcji płatności.
 */
class QuoteLandingShowsDisclaimerNextToPaymentTest extends TestCase
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

    public function test_disclaimer_visible_with_payment_url(): void
    {
        $token = str_repeat('a', 48);
        $this->makeQuote(QuoteStatus::Accepted, [
            'accept_token' => $token,
            'accepted_at' => now(),
            'payment_url' => 'https://buy.stripe.com/x',
        ]);

        $this->assertDisclaimerVisible($token);
    }

    public function test_disclaimer_visible_with_payment_completed(): void
    {
        $token = str_repeat('b', 48);
        $this->makeQuote(QuoteStatus::Accepted, [
            'accept_token' => $token,
            'accepted_at' => now()->subDay(),
            'payment_completed_at' => now(),
        ]);

        $this->assertDisclaimerVisible($token);
    }

    public function test_disclaimer_visible_with_payment_instructions_fallback(): void
    {
        TransportSettings::current()->forceFill([
            'payment_instructions' => 'Bank ABC',
        ])->save();

        $token = str_repeat('c', 48);
        $this->makeQuote(QuoteStatus::Accepted, [
            'accept_token' => $token,
            'accepted_at' => now(),
        ]);

        $this->assertDisclaimerVisible($token);
    }

    public function test_disclaimer_visible_with_no_payment_info(): void
    {
        $token = str_repeat('d', 48);
        $this->makeQuote(QuoteStatus::Accepted, [
            'accept_token' => $token,
            'accepted_at' => now(),
        ]);

        $this->assertDisclaimerVisible($token);
    }

    private function assertDisclaimerVisible(string $token): void
    {
        $response = $this->get("/transport/quote/{$this->tenant->slug}/{$token}");
        $response->assertOk();
        $response->assertSee('data-testid="payment-disclaimer"', escape: false);
        $response->assertSee('NIE przyjmuje płatności', escape: false);
        $response->assertSee('pośrednikiem marketplace', escape: false);
        // Klient ma wiedzieć kogo informować ws. reklamacji płatności.
        $response->assertSee('Reklamacje płatności kieruj bezpośrednio do przewoźnika', escape: false);
    }
}
