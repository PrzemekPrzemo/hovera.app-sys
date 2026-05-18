<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TransportLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TransportInquiryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_form_page(): void
    {
        $this->get('/transport/zapytanie')
            ->assertOk()
            ->assertSee('Zapytanie o transport koni');
    }

    public function test_submit_creates_lead_in_broadcast_mode(): void
    {
        $this->mockGeocoder();

        $response = $this->post('/transport/zapytanie', [
            'customer_name' => 'Jan Kowalski',
            'customer_email' => 'jan@example.com',
            'customer_phone' => '+48 600 100 200',
            'pickup_address' => 'Warszawa, Marymoncka 1',
            'dropoff_address' => 'Kraków, Krakusa 1',
            'preferred_date' => now()->addDays(5)->toDateString(),
            'preferred_time' => '08:00',
            'horse_count' => 2,
            'notes' => 'Konie hodowlane.',
            'terms' => '1',
        ]);

        $response->assertRedirect();
        $this->assertSame(1, TransportLead::count());

        $lead = TransportLead::first();
        $this->assertSame('broadcast', $lead->mode);
        $this->assertSame('open', $lead->status);
        $this->assertSame('Jan Kowalski', $lead->originator_name);
        $this->assertSame('jan@example.com', $lead->originator_email);
        $this->assertSame('mazowieckie', $lead->pickup_voivodeship);
        $this->assertSame('małopolskie', $lead->dropoff_voivodeship);
        $this->assertSame(2, $lead->horse_count);

        // Redirect leci do strony "dziekujemy" z ulid leadu
        $response->assertRedirect(route('public.transport.inquiry.thanks', ['lead' => $lead->id]));
    }

    public function test_submit_validates_required_fields(): void
    {
        $response = $this->post('/transport/zapytanie', []);

        $response->assertSessionHasErrors([
            'customer_name',
            'customer_email',
            'pickup_address',
            'dropoff_address',
            'preferred_date',
            'horse_count',
        ]);
        $this->assertSame(0, TransportLead::count());
    }

    public function test_submit_rejects_past_date(): void
    {
        $this->mockGeocoder();

        $this->post('/transport/zapytanie', [
            'customer_name' => 'Xy',
            'customer_email' => 'x@example.com',
            'pickup_address' => 'abc',
            'dropoff_address' => 'def',
            'preferred_date' => now()->subDay()->toDateString(),
            'horse_count' => 1,
            'terms' => '1',
        ])->assertSessionHasErrors(['preferred_date']);

        $this->assertSame(0, TransportLead::count());
    }

    public function test_submit_blocks_when_terms_not_accepted(): void
    {
        $this->mockGeocoder();

        $this->post('/transport/zapytanie', [
            'customer_name' => 'Xy',
            'customer_email' => 'x@example.com',
            'pickup_address' => 'abc',
            'dropoff_address' => 'def',
            'preferred_date' => now()->addDay()->toDateString(),
            'horse_count' => 1,
            // brak terms
        ])->assertSessionHasErrors(['terms']);

        $this->assertSame(0, TransportLead::count());
    }

    public function test_thanks_page_renders_with_lead_id(): void
    {
        $lead = TransportLead::create([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'mode' => 'broadcast',
            'pickup_address' => 'Warszawa',
            'pickup_lat' => 52.23, 'pickup_lng' => 21.01,
            'pickup_voivodeship' => 'mazowieckie',
            'dropoff_address' => 'Kraków',
            'dropoff_lat' => 50.04, 'dropoff_lng' => 19.94,
            'dropoff_voivodeship' => 'małopolskie',
            'preferred_date' => '2026-06-15',
            'horse_count' => 1,
            'status' => 'open',
            'expires_at' => now()->addDays(14),
            'originator_email' => 'test@example.com',
            'originator_name' => 'Test',
        ]);

        $this->get(route('public.transport.inquiry.thanks', ['lead' => $lead->id], false))
            ->assertOk()
            ->assertSee($lead->id)
            ->assertSee('test@example.com');
    }

    public function test_thanks_returns_404_for_unknown_lead(): void
    {
        // Walid ulid pattern ale rekord nie istnieje
        $bogus = str_repeat('0', 26);

        $this->get('/transport/zapytanie/dziekujemy/'.$bogus)
            ->assertNotFound();
    }

    public function test_geocoding_failure_keeps_form_with_error(): void
    {
        // Mapbox zwraca pustą listę features → GeocodingException::notFound
        Http::fake([
            'api.mapbox.com/*' => Http::response(['features' => []]),
        ]);
        config()->set('transport.providers.mapbox.access_token', 'pk.test');

        $this->post('/transport/zapytanie', [
            'customer_name' => 'Xy',
            'customer_email' => 'x@example.com',
            'pickup_address' => 'lokacja-bez-sensu',
            'dropoff_address' => 'lokacja-bez-sensu-2',
            'preferred_date' => now()->addDay()->toDateString(),
            'horse_count' => 1,
            'terms' => '1',
        ])->assertSessionHasErrors(['address']);

        $this->assertSame(0, TransportLead::count());
    }

    public function test_direct_mode_creates_lead_targeting_specific_transporter(): void
    {
        $this->mockGeocoder();
        $transporter = $this->makeTransporter('konie-trans', VerificationStatus::Verified);

        $response = $this->post('/transport/zapytanie?transporter=konie-trans', [
            'customer_name' => 'Jan Kowalski',
            'customer_email' => 'jan@example.com',
            'pickup_address' => 'Warszawa, Marymoncka 1',
            'dropoff_address' => 'Kraków, Krakusa 1',
            'preferred_date' => now()->addDays(5)->toDateString(),
            'horse_count' => 1,
            'terms' => '1',
        ]);

        $response->assertRedirect();
        $this->assertSame(1, TransportLead::count());

        $lead = TransportLead::first();
        $this->assertSame('direct', $lead->mode);
        $this->assertSame([$transporter->id], $lead->targeted_transporter_ids);
        $this->assertSame('open', $lead->status);
    }

    public function test_direct_mode_via_hidden_input_after_validation_redirect(): void
    {
        // Hidden input survives form re-submit (np. po geocoding error i poprawce).
        // Wysyłamy bez query-stringa — tylko POST body niesie slug.
        $this->mockGeocoder();
        $transporter = $this->makeTransporter('konie-trans', VerificationStatus::Verified);

        $this->post('/transport/zapytanie', [
            'transporter' => 'konie-trans',
            'customer_name' => 'Jan Kowalski',
            'customer_email' => 'jan@example.com',
            'pickup_address' => 'Warszawa, Marymoncka 1',
            'dropoff_address' => 'Kraków, Krakusa 1',
            'preferred_date' => now()->addDays(5)->toDateString(),
            'horse_count' => 1,
            'terms' => '1',
        ])->assertRedirect();

        $lead = TransportLead::first();
        $this->assertSame('direct', $lead->mode);
        $this->assertSame([$transporter->id], $lead->targeted_transporter_ids);
    }

    public function test_unverified_transporter_param_falls_back_to_broadcast(): void
    {
        $this->mockGeocoder();
        $this->makeTransporter('niezweryfikowany', VerificationStatus::Pending);

        $this->post('/transport/zapytanie?transporter=niezweryfikowany', [
            'customer_name' => 'Jan Kowalski',
            'customer_email' => 'jan@example.com',
            'pickup_address' => 'Warszawa, Marymoncka 1',
            'dropoff_address' => 'Kraków, Krakusa 1',
            'preferred_date' => now()->addDays(5)->toDateString(),
            'horse_count' => 1,
            'terms' => '1',
        ])->assertRedirect();

        $lead = TransportLead::first();
        $this->assertSame('broadcast', $lead->mode);
        $this->assertNull($lead->targeted_transporter_ids);
    }

    public function test_unknown_transporter_param_falls_back_to_broadcast(): void
    {
        $this->mockGeocoder();

        $this->post('/transport/zapytanie?transporter=nie-istnieje', [
            'customer_name' => 'Jan Kowalski',
            'customer_email' => 'jan@example.com',
            'pickup_address' => 'Warszawa, Marymoncka 1',
            'dropoff_address' => 'Kraków, Krakusa 1',
            'preferred_date' => now()->addDays(5)->toDateString(),
            'horse_count' => 1,
            'terms' => '1',
        ])->assertRedirect();

        $lead = TransportLead::first();
        $this->assertSame('broadcast', $lead->mode);
        $this->assertNull($lead->targeted_transporter_ids);
    }

    public function test_show_displays_direct_banner_when_transporter_query_present(): void
    {
        $this->makeTransporter('konie-trans', VerificationStatus::Verified);

        $response = $this->get('/transport/zapytanie?transporter=konie-trans');

        $response->assertOk()
            ->assertSee('Firma Konie trans', false)        // tenant name w banerze
            ->assertSee('bezpośrednio', false)             // fragment polish banner text
            ->assertSee('name="transporter"', false)       // hidden input survives POST
            ->assertSee('value="konie-trans"', false);
    }

    public function test_show_skips_direct_banner_for_unverified_transporter(): void
    {
        $this->makeTransporter('niezweryfikowany', VerificationStatus::Pending);

        $response = $this->get('/transport/zapytanie?transporter=niezweryfikowany');

        $response->assertOk()
            ->assertDontSee('bezpośrednio', false)
            ->assertDontSee('name="transporter"', false);
    }

    public function test_profile_cta_includes_transporter_slug(): void
    {
        $this->makeTransporter('konie-trans', VerificationStatus::Verified);

        $response = $this->get('/t/konie-trans');

        $response->assertOk()
            ->assertSee('transporter=konie-trans', false);
    }

    private function makeTransporter(string $slug, VerificationStatus $vs, string $status = 'active'): Tenant
    {
        // Cache spójny z TransporterProfileController/InquiryController — czyścimy
        // wpis aby świeżo utworzony tenant nie zderzył się z null-em z poprzedniego testu.
        Cache::forget("public_transporter:{$slug}");

        return Tenant::create([
            'slug' => $slug,
            'name' => 'Firma '.ucfirst(str_replace('-', ' ', $slug)),
            'type' => TenantType::Transporter,
            'verification_status' => $vs,
            'db_name' => 't_'.str_replace('-', '_', $slug),
            'db_username' => 't_'.str_replace('-', '_', $slug),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => $status,
        ]);
    }

    /**
     * Mock obu wywołań geocoder — pickup zwraca mazowieckie, dropoff małopolskie.
     */
    private function mockGeocoder(): void
    {
        config()->set('transport.providers.mapbox.access_token', 'pk.test');

        Http::fake([
            'api.mapbox.com/*' => Http::sequence()
                ->push([
                    'features' => [[
                        'place_name' => 'ul. Marymoncka 1, Warszawa, Polska',
                        'center' => [21.0122, 52.2297],
                        'context' => [
                            ['id' => 'region.1', 'text' => 'Mazowieckie'],
                        ],
                    ]],
                ])
                ->push([
                    'features' => [[
                        'place_name' => 'ul. Krakusa 1, Kraków, Polska',
                        'center' => [19.9362, 50.0413],
                        'context' => [
                            ['id' => 'region.2', 'text' => 'Małopolskie'],
                        ],
                    ]],
                ]),
        ]);
    }
}
