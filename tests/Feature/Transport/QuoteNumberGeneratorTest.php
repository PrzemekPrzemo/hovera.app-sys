<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\Quotes\QuoteNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QuoteNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_qnum_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        Schema::connection('tenant')->create('quote_counters', function ($t) {
            $t->string('scope', 32)->primary();
            $t->unsignedInteger('seq');
            $t->timestamp('updated_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_default_format_produces_ofyyyy_mm_nnnn(): void
    {
        $gen = new QuoteNumberGenerator();

        $issue = Carbon::create(2026, 5, 18);
        $this->assertSame('OF/2026/05/0001', $gen->next($issue));
        $this->assertSame('OF/2026/05/0002', $gen->next($issue));
        $this->assertSame('OF/2026/05/0003', $gen->next($issue));
    }

    public function test_monthly_reset_starts_new_month_at_one(): void
    {
        $gen = new QuoteNumberGenerator();

        $may = Carbon::create(2026, 5, 18);
        $june = Carbon::create(2026, 6, 1);

        $gen->next($may);
        $gen->next($may);   // 0002
        $this->assertSame('OF/2026/06/0001', $gen->next($june));
        $this->assertSame('OF/2026/06/0002', $gen->next($june));
    }

    public function test_yearly_reset_carries_within_year(): void
    {
        $gen = new QuoteNumberGenerator();

        $may = Carbon::create(2026, 5, 18);
        $june = Carbon::create(2026, 6, 1);

        $this->assertSame('OF/2026/05/0001', $gen->next($may, template: 'OF/{YYYY}/{MM}/{seq:4}', resetInterval: QuoteNumberGenerator::RESET_YEARLY));
        $this->assertSame('OF/2026/06/0002', $gen->next($june, template: 'OF/{YYYY}/{MM}/{seq:4}', resetInterval: QuoteNumberGenerator::RESET_YEARLY));
    }

    public function test_custom_template_with_prefix_and_seq_width(): void
    {
        $gen = new QuoteNumberGenerator();
        $issue = Carbon::create(2026, 12, 31);

        $this->assertSame('OFR/26-12-001', $gen->next($issue, template: 'OFR/{YY}-{MM}-{seq:3}'));
    }

    public function test_preview_does_not_increment_counter(): void
    {
        $gen = new QuoteNumberGenerator();

        $preview1 = $gen->preview(seq: 1, issueDate: Carbon::create(2026, 5, 18));
        $preview2 = $gen->preview(seq: 99, issueDate: Carbon::create(2026, 5, 18));

        $this->assertSame('OF/2026/05/0001', $preview1);
        $this->assertSame('OF/2026/05/0099', $preview2);

        // Po dwóch previewach counter NADAL nie powinien istnieć
        $first = $gen->next(Carbon::create(2026, 5, 18));
        $this->assertSame('OF/2026/05/0001', $first);
    }
}
