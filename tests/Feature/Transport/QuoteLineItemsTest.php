<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Models\Tenant\Quote;
use Tests\TestCase;

/**
 * PR "quote_items line items + PDF" — unit testy normalizatora i
 * sumatora line_items'ów na modelu Quote.
 *
 * E2E flow (Form → DB → PDF) jest pokryty pośrednio przez
 * QuoteResourceWorkflowTest + manual smoke. Tu fokus na czystej
 * logice biznesowej (defensive parse + auto-compute line_total_net).
 */
class QuoteLineItemsTest extends TestCase
{
    public function test_normalise_computes_line_total_from_qty_and_unit_price(): void
    {
        $out = Quote::normaliseLineItems([
            ['name' => 'Postój', 'quantity' => 4, 'unit' => 'h', 'unit_price_net' => 50],
        ]);

        $this->assertCount(1, $out);
        $this->assertSame(200.0, $out[0]['line_total_net']);
        $this->assertSame('h', $out[0]['unit']);
    }

    public function test_normalise_skips_items_without_name(): void
    {
        $out = Quote::normaliseLineItems([
            ['name' => '   ', 'quantity' => 1, 'unit_price_net' => 50],
            ['name' => 'Valid', 'unit_price_net' => 100],
        ]);

        $this->assertCount(1, $out);
        $this->assertSame('Valid', $out[0]['name']);
    }

    public function test_normalise_skips_zero_or_negative_unit_price(): void
    {
        $out = Quote::normaliseLineItems([
            ['name' => 'Free', 'unit_price_net' => 0],
            ['name' => 'Negative', 'unit_price_net' => -50],
            ['name' => 'Valid', 'unit_price_net' => 10],
        ]);

        $this->assertCount(1, $out);
        $this->assertSame('Valid', $out[0]['name']);
    }

    public function test_normalise_skips_zero_or_negative_quantity(): void
    {
        $out = Quote::normaliseLineItems([
            ['name' => 'Zero qty', 'quantity' => 0, 'unit_price_net' => 50],
            ['name' => 'Negative qty', 'quantity' => -2, 'unit_price_net' => 50],
            ['name' => 'Valid', 'quantity' => 2, 'unit_price_net' => 50],
        ]);

        $this->assertCount(1, $out);
        $this->assertSame(100.0, $out[0]['line_total_net']);
    }

    public function test_normalise_defaults_quantity_to_one(): void
    {
        $out = Quote::normaliseLineItems([
            ['name' => 'Single', 'unit_price_net' => 75],
        ]);

        $this->assertSame(1.0, $out[0]['quantity']);
        $this->assertSame(75.0, $out[0]['line_total_net']);
    }

    public function test_normalise_handles_fractional_quantities(): void
    {
        $out = Quote::normaliseLineItems([
            ['name' => 'Półgodziny', 'quantity' => 0.5, 'unit_price_net' => 100],
        ]);

        $this->assertSame(0.5, $out[0]['quantity']);
        $this->assertSame(50.0, $out[0]['line_total_net']);
    }

    public function test_normalise_skips_non_array_garbage(): void
    {
        $out = Quote::normaliseLineItems([
            'not-an-array',
            null,
            ['name' => 'Valid', 'unit_price_net' => 25],
        ]);

        $this->assertCount(1, $out);
        $this->assertSame('Valid', $out[0]['name']);
    }

    public function test_line_items_total_sums_line_total_net(): void
    {
        $quote = new Quote;
        $quote->line_items = [
            ['name' => 'A', 'line_total_net' => 100],
            ['name' => 'B', 'line_total_net' => 50.25],
            ['name' => 'C', 'line_total_net' => 0],
        ];

        $this->assertSame(150.25, $quote->lineItemsTotal());
    }

    public function test_line_items_total_returns_zero_for_null_items(): void
    {
        $quote = new Quote;
        $quote->line_items = null;

        $this->assertSame(0.0, $quote->lineItemsTotal());
    }

    public function test_line_items_total_returns_zero_for_empty_array(): void
    {
        $quote = new Quote;
        $quote->line_items = [];

        $this->assertSame(0.0, $quote->lineItemsTotal());
    }
}
