<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Directory;

use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TransportLead;
use App\Models\Central\TransportLeadResponse;
use App\Models\Central\TransportReview;
use App\Models\Central\TransportServiceArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransporterDirectoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_lists_verified_transporters(): void
    {
        $a = $this->makeTransporter('alfa', VerificationStatus::Verified);
        $b = $this->makeTransporter('beta', VerificationStatus::Verified);

        $this->get('/przewoznicy')
            ->assertOk()
            ->assertSee($a->name, false)
            ->assertSee($b->name, false);
    }

    public function test_excludes_pending_and_rejected_transporters(): void
    {
        $this->makeTransporter('verified-co', VerificationStatus::Verified);
        $this->makeTransporter('pending-co', VerificationStatus::Pending);
        $this->makeTransporter('rejected-co', VerificationStatus::Rejected);
        $this->makeTransporter('under-review-co', VerificationStatus::UnderReview);

        $response = $this->get('/przewoznicy')->assertOk();
        $response->assertSee('Firma Verified Co', false);
        $response->assertDontSee('Firma Pending Co', false);
        $response->assertDontSee('Firma Rejected Co', false);
        $response->assertDontSee('Firma Under Review Co', false);
    }

    public function test_excludes_suspended_tenants(): void
    {
        $this->makeTransporter('active-firm', VerificationStatus::Verified, status: 'active');
        $this->makeTransporter('suspended-firm', VerificationStatus::Verified, status: 'suspended');
        $this->makeTransporter('churned-firm', VerificationStatus::Verified, status: 'churned');

        $response = $this->get('/przewoznicy')->assertOk();
        $response->assertSee('Firma Active Firm', false);
        $response->assertDontSee('Firma Suspended Firm', false);
        $response->assertDontSee('Firma Churned Firm', false);
    }

    public function test_excludes_stable_tenants(): void
    {
        $this->makeTransporter('przewoz-x', VerificationStatus::Verified);

        Tenant::create([
            'slug' => 'stajnia-y',
            'name' => 'Stajnia Y',
            'type' => TenantType::Stable,
            'db_name' => 's_'.uniqid(),
            'db_username' => 's_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);

        $this->get('/przewoznicy')
            ->assertOk()
            ->assertDontSee('Stajnia Y', false);
    }

    public function test_filter_by_voivodeship_returns_matching_transporters_only(): void
    {
        $mazowsze = $this->makeTransporter('maz-firma', VerificationStatus::Verified);
        $slask = $this->makeTransporter('sl-firma', VerificationStatus::Verified);

        TransportServiceArea::create([
            'transporter_tenant_id' => $mazowsze->id,
            'voivodeship' => 'mazowieckie',
        ]);
        TransportServiceArea::create([
            'transporter_tenant_id' => $slask->id,
            'voivodeship' => 'śląskie',
        ]);

        $response = $this->get('/przewoznicy?voivodeship=mazowieckie')->assertOk();
        $response->assertSee('Firma Maz Firma', false);
        $response->assertDontSee('Firma Sl Firma', false);
    }

    public function test_invalid_voivodeship_is_ignored(): void
    {
        $this->makeTransporter('whatever', VerificationStatus::Verified);

        // Niedozwolone województwo nie powinno przeciąć całej listy do zera.
        $this->get('/przewoznicy?voivodeship=mordor')
            ->assertOk()
            ->assertSee('Firma Whatever', false);
    }

    public function test_search_by_name_case_insensitive(): void
    {
        $this->makeTransporterWithName('a-firm', 'EquiTrans Polska');
        $this->makeTransporterWithName('b-firm', 'Konie Express');

        $r1 = $this->get('/przewoznicy?q=equitrans')->assertOk();
        $r1->assertSee('EquiTrans Polska', false);
        $r1->assertDontSee('Konie Express', false);

        $r2 = $this->get('/przewoznicy?q=KONIE')->assertOk();
        $r2->assertSee('Konie Express', false);
        $r2->assertDontSee('EquiTrans Polska', false);
    }

    public function test_sort_by_rating_desc_returns_highest_rated_first(): void
    {
        $low = $this->makeTransporterWithName('low-rated', 'Firma Niska');
        $high = $this->makeTransporterWithName('high-rated', 'Firma Wysoka');

        $this->makePublishedReview($low, 2);
        $this->makePublishedReview($low, 3);
        $this->makePublishedReview($high, 5);
        $this->makePublishedReview($high, 5);

        $html = $this->get('/przewoznicy?sort=rating_desc')->assertOk()->getContent();

        $posHigh = strpos((string) $html, 'Firma Wysoka');
        $posLow = strpos((string) $html, 'Firma Niska');
        $this->assertNotFalse($posHigh, 'Firma Wysoka should be present');
        $this->assertNotFalse($posLow, 'Firma Niska should be present');
        $this->assertLessThan($posLow, $posHigh, 'Higher-rated firm must appear before lower-rated firm');
    }

    public function test_sort_by_name_orders_alphabetically(): void
    {
        $this->makeTransporterWithName('zzz-slug', 'Zebra Transport');
        $this->makeTransporterWithName('aaa-slug', 'Alfa Transport');

        $html = $this->get('/przewoznicy?sort=name')->assertOk()->getContent();
        $posA = strpos((string) $html, 'Alfa Transport');
        $posZ = strpos((string) $html, 'Zebra Transport');
        $this->assertNotFalse($posA);
        $this->assertNotFalse($posZ);
        $this->assertLessThan($posZ, $posA);
    }

    public function test_pagination_works(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->makeTransporter('firma-'.$i, VerificationStatus::Verified);
        }

        $r1 = $this->get('/przewoznicy?sort=name');
        $r1->assertOk();
        // Pagination links present.
        $r1->assertSee('page=2', false);

        // Page 2 should also return OK with fewer items (5 remaining).
        $this->get('/przewoznicy?sort=name&page=2')->assertOk();
    }

    public function test_empty_state_when_no_results(): void
    {
        $this->makeTransporterWithName('aktywny', 'Aktywny Trans');

        $this->get('/przewoznicy?q=nieistniejaca-firma-xyz')
            ->assertOk()
            ->assertSee(__('public/transporter_directory.empty_state_title'), false);
    }

    public function test_cache_control_header_set(): void
    {
        $this->makeTransporter('cached-firm', VerificationStatus::Verified);

        $cc = $this->get('/przewoznicy')->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cc);
        $this->assertStringContainsString('max-age=60', $cc);
        $this->assertStringContainsString('s-maxage=300', $cc);
    }

    public function test_includes_disclaimer_with_marketplace_terms_link(): void
    {
        $this->makeTransporter('cool-firm', VerificationStatus::Verified);

        $this->get('/przewoznicy')
            ->assertOk()
            ->assertSee(__('public/transporter_directory.card_disclaimer_verified'), false)
            ->assertSee('/regulamin-marketplace', false);
    }

    public function test_includes_canonical_and_robots_meta(): void
    {
        $this->get('/przewoznicy')
            ->assertOk()
            ->assertSee('<link rel="canonical"', false)
            ->assertSee('/przewoznicy', false)
            ->assertSee('index, follow', false);
    }

    public function test_includes_cta_to_inquiry_and_signup(): void
    {
        $this->get('/przewoznicy')
            ->assertOk()
            ->assertSee('/transport/zapytanie', false)
            ->assertSee('/signup?type=transporter', false);
    }

    public function test_review_aggregate_computed_without_n_plus_1(): void
    {
        // Tworzymy 12 verified transporterów + po 2 opinie każdy.
        $tenants = [];
        for ($i = 0; $i < 12; $i++) {
            $tenant = $this->makeTransporter('npn-'.$i, VerificationStatus::Verified);
            $this->makePublishedReview($tenant, 5);
            $this->makePublishedReview($tenant, 4);
            $tenants[] = $tenant;
        }

        Cache::flush();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get('/przewoznicy?sort=name')->assertOk();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Sanity ceiling — request powinno robić wyraźnie mniej zapytań
        // niż per-tenant (12 tenantów * 2 = 24 dodatkowe). Limit z dużym
        // bufforem na session/foreign-key checks ale wciąż łapie regresję
        // gdyby ktoś pętlą wołał TransportReview::aggregateFor().
        $this->assertLessThanOrEqual(
            12,
            $queryCount,
            "Expected ≤ 12 queries for 12-tenant page (anti-N+1). Got: {$queryCount}"
        );
    }

    public function test_clear_filters_link_resets_state(): void
    {
        $this->makeTransporter('any', VerificationStatus::Verified);

        $response = $this->get('/przewoznicy?q=foo&voivodeship=mazowieckie');
        $response->assertOk();
        $response->assertSee(__('public/transporter_directory.clear_filters'), false);
    }

    private function makeTransporter(
        string $slug,
        VerificationStatus $vs,
        string $status = 'active',
    ): Tenant {
        return Tenant::create([
            'slug' => $slug,
            'name' => 'Firma '.ucwords(str_replace('-', ' ', $slug)),
            'type' => TenantType::Transporter,
            'verification_status' => $vs,
            'db_name' => 't_'.uniqid().'_'.str_replace('-', '_', $slug),
            'db_username' => 't_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => $status,
        ]);
    }

    private function makeTransporterWithName(string $slug, string $name): Tenant
    {
        return Tenant::create([
            'slug' => $slug,
            'name' => $name,
            'type' => TenantType::Transporter,
            'verification_status' => VerificationStatus::Verified,
            'db_name' => 't_'.uniqid().'_'.str_replace('-', '_', $slug),
            'db_username' => 't_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }

    private function makePublishedReview(Tenant $tenant, int $rating): TransportReview
    {
        $lead = TransportLead::create([
            'id' => (string) Str::ulid(),
            'mode' => 'broadcast',
            'pickup_address' => 'a', 'pickup_lat' => 0, 'pickup_lng' => 0, 'pickup_voivodeship' => 'maz',
            'dropoff_address' => 'b', 'dropoff_lat' => 0, 'dropoff_lng' => 0, 'dropoff_voivodeship' => 'mal',
            'preferred_date' => now()->subDays(30)->toDateString(),
            'horse_count' => 1,
            'status' => 'accepted',
            'expires_at' => now()->subDays(20),
            'originator_email' => 'k@example.com',
        ]);
        $response = TransportLeadResponse::create([
            'id' => (string) Str::ulid(),
            'lead_id' => $lead->id,
            'transporter_tenant_id' => $tenant->id,
            'price_net' => 1, 'price_gross' => 1, 'currency' => 'PLN',
            'proposed_date' => $lead->preferred_date,
            'status' => 'accepted',
        ]);

        return TransportReview::create([
            'transporter_tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'response_id' => $response->id,
            'invite_token_hash' => hash('sha256', Str::random(48).Str::random(8)),
            'invite_sent_at' => now()->subDays(5),
            'invite_expires_at' => now()->addDays(25),
            'customer_email_hash' => hash('sha256', 'k@example.com'),
            'customer_email_redacted' => 'k***@example.com',
            'rating' => $rating,
            'status' => 'published',
            'submitted_at' => now(),
        ]);
    }
}
