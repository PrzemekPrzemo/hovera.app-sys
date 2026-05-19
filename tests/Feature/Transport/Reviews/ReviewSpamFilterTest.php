<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Reviews;

use App\Domain\Transport\Reviews\ReviewSpamFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * `ReviewSpamFilter` — anti-spam dla publicznego submit review.
 * Patrz docs/TRANSPORT.md §12.
 */
class ReviewSpamFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_review_is_not_spam(): void
    {
        $filter = new ReviewSpamFilter;

        $this->assertFalse($filter->isSpam([
            'rating' => 5,
            'comment' => 'Świetny transport, polecam! Pojazd schludny, kierowca punktualny.',
            'customer_name' => 'Anna Nowak',
            'transporter_tenant_id' => '01HXY',
        ]));
    }

    public function test_keyword_in_comment_flagged_as_spam(): void
    {
        $filter = new ReviewSpamFilter;

        $this->assertTrue($filter->isSpam([
            'rating' => 5,
            'comment' => 'Świetna firma! Visit our casino site for free spins!',
            'customer_name' => 'Anna Nowak',
            'transporter_tenant_id' => '01HXY',
        ]));
    }

    public function test_keyword_in_customer_name_flagged_as_spam(): void
    {
        $filter = new ReviewSpamFilter;

        $this->assertTrue($filter->isSpam([
            'rating' => 5,
            'comment' => 'OK',
            'customer_name' => 'Bitcoin gift winner', // promo crap
            'transporter_tenant_id' => '01HXY',
        ]));
    }

    public function test_url_in_comment_flagged_as_spam(): void
    {
        $filter = new ReviewSpamFilter;

        $this->assertTrue($filter->isSpam([
            'rating' => 1,
            'comment' => 'Sprawdź http://my-scam-site.com żeby kupić tanio',
            'customer_name' => 'Test',
            'transporter_tenant_id' => '01HXY',
        ]));
    }

    public function test_pl_slurs_flagged(): void
    {
        $filter = new ReviewSpamFilter;

        $this->assertTrue($filter->isSpam([
            'rating' => 1,
            'comment' => 'Spierdalaj z taką firmą, jebać was wszystkich',
            'customer_name' => 'Anonim',
            'transporter_tenant_id' => '01HXY',
        ]));
    }

    public function test_case_insensitive_match(): void
    {
        $filter = new ReviewSpamFilter;

        $this->assertTrue($filter->isSpam([
            'rating' => 1,
            'comment' => 'VIAGRA Special Discount Today!!!',
            'customer_name' => 'X',
            'transporter_tenant_id' => '01HXY',
        ]));
    }

    public function test_duplicate_review_within_30min_flagged(): void
    {
        $filter = new ReviewSpamFilter;
        $tenantId = '01HABC123456789012345678901';
        $comment = 'Bardzo dobry transport, polecam wszystkim!';

        // Raw insert pomija FK check'i dla lead_id/response_id —
        // testujemy tylko logikę spam-filtru, nie integralność relacji.
        DB::connection('central')
            ->statement('PRAGMA foreign_keys = OFF');

        DB::connection('central')
            ->table('transport_reviews')->insert([
                'id' => (string) Str::ulid(),
                'transporter_tenant_id' => $tenantId,
                'lead_id' => (string) Str::ulid(),
                'response_id' => (string) Str::ulid(),
                'customer_email_hash' => hash('sha256', 'spamtest@example.com'),
                'rating' => 5,
                'comment' => $comment,
                'customer_name' => 'First User',
                'invite_token_hash' => str_repeat('a', 64),
                'invite_sent_at' => now()->subHour(),
                'invite_expires_at' => now()->addDay(),
                'status' => 'published',
                'submitted_at' => now()->subMinutes(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $this->assertTrue($filter->isSpam([
            'rating' => 5,
            'comment' => $comment,
            'customer_name' => 'Different User',
            'transporter_tenant_id' => $tenantId,
        ]));
    }

    public function test_duplicate_after_30min_window_not_flagged(): void
    {
        $filter = new ReviewSpamFilter;
        $tenantId = '01HABC123456789012345678902';
        $comment = 'Solidna firma, transport bez zarzutu.';

        DB::connection('central')
            ->statement('PRAGMA foreign_keys = OFF');

        DB::connection('central')
            ->table('transport_reviews')->insert([
                'id' => (string) Str::ulid(),
                'transporter_tenant_id' => $tenantId,
                'lead_id' => (string) Str::ulid(),
                'response_id' => (string) Str::ulid(),
                'customer_email_hash' => hash('sha256', 'spamtest@example.com'),
                'rating' => 5,
                'comment' => $comment,
                'customer_name' => 'First',
                'invite_token_hash' => str_repeat('b', 64),
                'invite_sent_at' => now()->subHours(2),
                'invite_expires_at' => now()->addDay(),
                'status' => 'published',
                'submitted_at' => now()->subMinutes(31),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $this->assertFalse($filter->isSpam([
            'rating' => 5,
            'comment' => $comment,
            'customer_name' => 'Second',
            'transporter_tenant_id' => $tenantId,
        ]));
    }

    public function test_duplicate_for_different_tenant_not_flagged(): void
    {
        $filter = new ReviewSpamFilter;
        $tenantA = '01HABC123456789012345678903';
        $tenantB = '01HABC123456789012345678904';
        $comment = 'Bardzo dobry transport, polecam!';

        DB::connection('central')
            ->statement('PRAGMA foreign_keys = OFF');

        DB::connection('central')
            ->table('transport_reviews')->insert([
                'id' => (string) Str::ulid(),
                'transporter_tenant_id' => $tenantA,
                'lead_id' => (string) Str::ulid(),
                'response_id' => (string) Str::ulid(),
                'customer_email_hash' => hash('sha256', 'spamtest@example.com'),
                'rating' => 5,
                'comment' => $comment,
                'customer_name' => 'X',
                'invite_token_hash' => str_repeat('c', 64),
                'invite_sent_at' => now()->subHour(),
                'invite_expires_at' => now()->addDay(),
                'status' => 'published',
                'submitted_at' => now()->subMinutes(5),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $this->assertFalse($filter->isSpam([
            'rating' => 5,
            'comment' => $comment,
            'customer_name' => 'Y',
            'transporter_tenant_id' => $tenantB,
        ]));
    }

    public function test_empty_comment_not_flagged(): void
    {
        // Same gwiazdki bez komentarza — całkowicie legit (klient nie chce pisać).
        $filter = new ReviewSpamFilter;

        $this->assertFalse($filter->isSpam([
            'rating' => 4,
            'comment' => '',
            'customer_name' => 'Krótki feedback',
            'transporter_tenant_id' => '01HXY',
        ]));
    }
}
