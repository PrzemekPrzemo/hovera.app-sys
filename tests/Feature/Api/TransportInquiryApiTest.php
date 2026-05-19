<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Transport\Leads\LeadDispatcher;
use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TransportLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Embed snippet JSON API — `POST /api/transport/inquiry`. Sprawdza pełen
 * defense-in-depth: per-tenant CORS + token + honeypot + walidacja + throttle.
 * Patrz docs/TRANSPORT.md §16.
 *
 * Config (origins + token) żyje na central `tenants`, więc testy nie potrzebują
 * tenant DB switching.
 */
class TransportInquiryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Throttle 10/h per IP. Wszystkie testy z 127.0.0.1 — flushujemy cache
        // żeby kolejność testów nie sprawiała że throttle wykorzystany.
        cache()->flush();
    }

    public function test_unknown_slug_returns_404(): void
    {
        $this->postJson('/api/transport/inquiry', [
            'transporter_slug' => 'nope',
        ] + $this->validPayload())->assertStatus(404);
    }

    public function test_unverified_transporter_returns_404(): void
    {
        $tenant = $this->makeTransporter(VerificationStatus::Pending);

        $this->postJson('/api/transport/inquiry', [
            'transporter_slug' => $tenant->slug,
        ] + $this->validPayload())->assertStatus(404);
    }

    public function test_missing_token_returns_403(): void
    {
        $tenant = $this->makeVerifiedWithEmbed();

        $this->postJson('/api/transport/inquiry', [
            'transporter_slug' => $tenant->slug,
        ] + $this->validPayload())
            ->assertStatus(403)
            ->assertJsonPath('errors.token.0', 'Invalid or missing X-Hovera-Embed-Token header.');
    }

    public function test_wrong_token_returns_403(): void
    {
        $tenant = $this->makeVerifiedWithEmbed();

        $this->withHeaders(['X-Hovera-Embed-Token' => 'wrong-token'])
            ->postJson('/api/transport/inquiry', [
                'transporter_slug' => $tenant->slug,
            ] + $this->validPayload())
            ->assertStatus(403);
    }

    public function test_honeypot_returns_silent_200_without_creating_lead(): void
    {
        $tenant = $this->makeVerifiedWithEmbed();

        $this->postJson('/api/transport/inquiry', [
            'transporter_slug' => $tenant->slug,
            'website' => 'http://spam.bot',  // honeypot
        ] + $this->validPayload())
            ->assertStatus(200)
            ->assertJson(['status' => 'ok', 'inquiry_id' => null]);

        $this->assertSame(0, TransportLead::count());
    }

    public function test_valid_payload_creates_lead_in_direct_mode_targeting_tenant(): void
    {
        $tenant = $this->makeVerifiedWithEmbed();
        $this->mockGeocoder();
        $this->mock(LeadDispatcher::class, function ($m) {
            $m->shouldReceive('dispatch')->once();
        });

        $response = $this->withHeaders(['X-Hovera-Embed-Token' => 'right-token'])
            ->postJson('/api/transport/inquiry', [
                'transporter_slug' => $tenant->slug,
            ] + $this->validPayload());

        $response->assertStatus(200)->assertJsonStructure(['status', 'inquiry_id']);

        $lead = TransportLead::first();
        $this->assertNotNull($lead);
        $this->assertSame('direct', $lead->mode);
        $this->assertSame([$tenant->id], $lead->targeted_transporter_ids);
        $this->assertSame('Anna Nowak', $lead->originator_name);
    }

    public function test_validation_errors_returned_as_422(): void
    {
        $tenant = $this->makeVerifiedWithEmbed();

        $this->withHeaders(['X-Hovera-Embed-Token' => 'right-token'])
            ->postJson('/api/transport/inquiry', [
                'transporter_slug' => $tenant->slug,
                'customer_name' => '',  // required violation
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_name', 'customer_email']);
    }

    public function test_preflight_options_with_allowed_origin_returns_cors_headers(): void
    {
        $tenant = $this->makeVerifiedWithEmbed();

        $response = $this->call(
            method: 'OPTIONS',
            uri: '/api/transport/inquiry?transporter_slug='.$tenant->slug,
            server: ['HTTP_ORIGIN' => 'https://blog.example.com'],
        );

        $response->assertNoContent(204);
        $this->assertSame('https://blog.example.com', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertStringContainsString('POST', (string) $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertStringContainsString('X-Hovera-Embed-Token', (string) $response->headers->get('Access-Control-Allow-Headers'));
    }

    public function test_preflight_options_with_disallowed_origin_no_cors_headers(): void
    {
        $tenant = $this->makeVerifiedWithEmbed();

        $response = $this->call(
            method: 'OPTIONS',
            uri: '/api/transport/inquiry?transporter_slug='.$tenant->slug,
            server: ['HTTP_ORIGIN' => 'https://evil.example.com'],
        );

        $response->assertNoContent(204);
        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_post_from_allowed_origin_sets_cors_headers_on_response(): void
    {
        $tenant = $this->makeVerifiedWithEmbed();
        $this->mockGeocoder();
        $this->mock(LeadDispatcher::class, function ($m) {
            $m->shouldReceive('dispatch')->once();
        });

        $response = $this->withHeaders([
            'X-Hovera-Embed-Token' => 'right-token',
            'Origin' => 'https://blog.example.com',
        ])->postJson('/api/transport/inquiry', [
            'transporter_slug' => $tenant->slug,
        ] + $this->validPayload());

        $response->assertStatus(200);
        $this->assertSame('https://blog.example.com', $response->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * Tenant z verified + embed_allowed_origins=[blog.example.com]
     * + embed_api_token='right-token'.
     */
    private function makeVerifiedWithEmbed(): Tenant
    {
        $tenant = $this->makeTransporter(VerificationStatus::Verified);
        $tenant->embed_allowed_origins = ['https://blog.example.com'];
        $tenant->embed_api_token = 'right-token';
        $tenant->save();

        return $tenant->fresh();
    }

    private function makeTransporter(VerificationStatus $status): Tenant
    {
        return Tenant::create([
            'slug' => 't-'.uniqid(),
            'name' => 'Firma Test',
            'type' => TenantType::Transporter,
            'verification_status' => $status,
            'db_name' => 't_'.uniqid(),
            'db_username' => 't_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }

    private function mockGeocoder(): void
    {
        config()->set('transport.providers.mapbox.access_token', 'pk.test');

        Http::fake([
            'api.mapbox.com/*' => Http::sequence()
                ->push([
                    'features' => [[
                        'place_name' => 'Warszawa, Polska',
                        'center' => [21.0122, 52.2297],
                        'context' => [['id' => 'region.1', 'text' => 'Mazowieckie']],
                    ]],
                ])
                ->push([
                    'features' => [[
                        'place_name' => 'Kraków, Polska',
                        'center' => [19.9362, 50.0413],
                        'context' => [['id' => 'region.2', 'text' => 'Małopolskie']],
                    ]],
                ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'customer_name' => 'Anna Nowak',
            'customer_email' => 'anna@test.pl',
            'customer_phone' => '+48 600 100 200',
            'pickup_address' => 'Warszawa, Marymoncka 1',
            'dropoff_address' => 'Kraków, Krakusa 1',
            'preferred_date' => now()->addDays(5)->toDateString(),
            'preferred_time' => '08:00',
            'horse_count' => 2,
            'terms' => true,
        ];
    }
}
