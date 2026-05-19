<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Payments\Stripe;

use App\Domain\Transport\Payments\Stripe\TransporterStripeConnectService;
use App\Enums\QuoteStatus;
use App\Enums\TenantType;
use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use RuntimeException;
use Stripe\Account;
use Stripe\StripeObject;
use Tests\Feature\Transport\Payments\PaymentTestTenantSetup;
use Tests\TestCase;

/**
 * Stripe Connect Express — patrz docs/TRANSPORT.md §15.6.
 *
 * Testy logiki TransporterStripeConnectService — bez wywoływania prawdziwego
 * Stripe API. mapAccountStatus jest pure (Account object → string), więc
 * testujemy z różnymi kombinacjami charges_enabled / requirements.disabled_reason.
 * Walidacje (guardTransporter, createCheckoutSession bez enabled) testujemy
 * przez próbę wywołania.
 */
class TransporterStripeConnectServiceTest extends TestCase
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

    public function test_constructor_rejects_empty_secret(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TransporterStripeConnectService(secret: '');
    }

    public function test_constructor_rejects_out_of_range_application_fee(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TransporterStripeConnectService(
            secret: 'sk_test_x',
            applicationFeePercent: 150.0,
        );
    }

    public function test_map_account_status_enabled_when_charges_enabled(): void
    {
        $svc = $this->service();
        $account = $this->makeAccount(['charges_enabled' => true, 'details_submitted' => true]);

        $this->assertSame('enabled', $svc->mapAccountStatus($account));
    }

    public function test_map_account_status_pending_when_kyc_not_submitted(): void
    {
        $svc = $this->service();
        $account = $this->makeAccount([
            'charges_enabled' => false,
            'details_submitted' => false,
            'requirements' => ['disabled_reason' => ''],
        ]);

        $this->assertSame('pending', $svc->mapAccountStatus($account));
    }

    public function test_map_account_status_restricted_when_disabled_reason_set(): void
    {
        $svc = $this->service();
        $account = $this->makeAccount([
            'charges_enabled' => false,
            'details_submitted' => true,
            'requirements' => ['disabled_reason' => 'requirements.past_due'],
        ]);

        $this->assertSame('restricted', $svc->mapAccountStatus($account));
    }

    public function test_map_account_status_rejected_when_stripe_rejected(): void
    {
        $svc = $this->service();
        $account = $this->makeAccount([
            'charges_enabled' => false,
            'requirements' => ['disabled_reason' => 'rejected.fraud'],
        ]);

        $this->assertSame('rejected', $svc->mapAccountStatus($account));
    }

    public function test_create_checkout_session_fails_when_tenant_not_enabled(): void
    {
        $svc = $this->service();
        $quote = $this->makeQuote(QuoteStatus::Accepted, [
            'accept_token' => str_repeat('q', 48),
            'gross_total' => 1694.66,
        ]);

        // Tenant nie ma Stripe Connect włączonego.
        $this->tenant->forceFill([
            'stripe_connect_account_id' => null,
            'stripe_connect_status' => 'none',
        ])->save();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no enabled Stripe Connect account');
        $svc->createCheckoutSession(
            $quote, $this->tenant->fresh(),
            'https://example.com/ok', 'https://example.com/no',
        );
    }

    public function test_create_checkout_session_fails_on_zero_amount(): void
    {
        $svc = $this->service();
        $this->tenant->forceFill([
            'stripe_connect_account_id' => 'acct_test_123',
            'stripe_connect_status' => 'enabled',
        ])->save();

        $quote = $this->makeQuote(QuoteStatus::Accepted, [
            'accept_token' => str_repeat('z', 48),
            'gross_total' => 0,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('non-positive gross_total');
        $svc->createCheckoutSession(
            $quote, $this->tenant->fresh(),
            'https://example.com/ok', 'https://example.com/no',
        );
    }

    public function test_guard_transporter_rejects_stable(): void
    {
        $stable = Tenant::create([
            'slug' => 'stajnia-'.uniqid(),
            'name' => 'Stajnia',
            'type' => TenantType::Stable,
            'db_name' => 'st_'.uniqid(),
            'db_username' => 'st_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);

        $svc = $this->service();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('transporters only');
        $svc->createConnectAccount($stable);
    }

    public function test_find_tenant_by_stripe_account_returns_matching_tenant(): void
    {
        $this->tenant->forceFill(['stripe_connect_account_id' => 'acct_unique_x'])->save();

        $found = TransporterStripeConnectService::findTenantByStripeAccount('acct_unique_x');
        $this->assertNotNull($found);
        $this->assertSame($this->tenant->id, $found->id);
    }

    public function test_find_tenant_by_stripe_account_returns_null_for_unknown(): void
    {
        $found = TransporterStripeConnectService::findTenantByStripeAccount('acct_does_not_exist');
        $this->assertNull($found);
    }

    public function test_has_stripe_connect_enabled_helper(): void
    {
        $this->tenant->forceFill([
            'stripe_connect_account_id' => 'acct_x',
            'stripe_connect_status' => 'enabled',
        ])->save();
        $this->assertTrue($this->tenant->fresh()->hasStripeConnectEnabled());

        $this->tenant->forceFill(['stripe_connect_status' => 'pending'])->save();
        $this->assertFalse($this->tenant->fresh()->hasStripeConnectEnabled());
    }

    private function service(): TransporterStripeConnectService
    {
        return new TransporterStripeConnectService(
            secret: 'sk_test_123_dummy',
            country: 'PL',
            applicationFeePercent: 0.0,
        );
    }

    /**
     * Buduje Stripe\Account z surowych danych (bez API calla) — Stripe
     * SDK pozwala na `Account::constructFrom($data)` ale to dla unit testów
     * mapowania wystarczy StripeObject z polami które czyta mapAccountStatus.
     *
     * @param  array<string,mixed>  $data
     */
    private function makeAccount(array $data): Account
    {
        $account = Account::constructFrom(array_merge(['id' => 'acct_test_'.uniqid()], $data));

        // mapAccountStatus czyta requirements jako object lub array — Stripe
        // SDK po constructFrom robi z nested array StripeObject, więc replikujemy.
        if (isset($data['requirements']) && is_array($data['requirements'])) {
            $req = StripeObject::constructFrom($data['requirements']);
            $account->requirements = $req;
        }

        return $account;
    }
}
