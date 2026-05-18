<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Payments;

use App\Enums\QuoteStatus;
use App\Filament\Transport\Resources\QuoteResource;
use App\Services\TenantAuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Patrz docs/TRANSPORT.md §13.
 *
 * Ręczne potwierdzanie wpływu — Hovera nie ma webhooków (direct charge bez
 * API integracji), więc transporter klika "Oznacz jako opłacone" po wpływie.
 * Akcja loguje do audytu.
 */
class MarkAsPaidActionTest extends TestCase
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

    public function test_mark_as_paid_sets_completed_at_and_audit_logs(): void
    {
        // Re-mock audit logger with specific expectation (overrides trait default).
        $this->mock(TenantAuditLogger::class, function (MockInterface $m) {
            $m->shouldReceive('record')
                ->once()
                ->with('quote.mark_as_paid', 'Quote', \Mockery::type('string'), \Mockery::on(function (array $payload) {
                    return ($payload['reason'] ?? null) === 'BLIK 1234';
                }))
                ->andReturnNull();
        });

        $quote = $this->makeQuote(QuoteStatus::Accepted, [
            'accepted_at' => now()->subHour(),
            'payment_url' => 'https://buy.stripe.com/x',
        ]);

        $this->assertNull($quote->payment_completed_at);

        QuoteResource::markAsPaid($quote, 'BLIK 1234');

        $this->assertNotNull($quote->fresh()->payment_completed_at);
    }

    public function test_mark_as_paid_is_idempotent_if_already_paid(): void
    {
        $this->mock(TenantAuditLogger::class, function (MockInterface $m) {
            $m->shouldNotReceive('record');
        });

        $original = now()->subDay();
        $quote = $this->makeQuote(QuoteStatus::Accepted, [
            'accepted_at' => now()->subDays(2),
            'payment_completed_at' => $original,
        ]);

        QuoteResource::markAsPaid($quote, 'should-not-log');

        // payment_completed_at unchanged (idempotent).
        $this->assertSame(
            $original->format('Y-m-d H:i:s'),
            $quote->fresh()->payment_completed_at->format('Y-m-d H:i:s'),
        );
    }
}
