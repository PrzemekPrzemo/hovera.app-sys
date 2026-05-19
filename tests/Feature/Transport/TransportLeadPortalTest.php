<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Mail\Customer\TransportLeadAccessMail;
use App\Models\Central\Tenant;
use App\Models\Central\TransportLead;
use App\Models\Central\TransportLeadResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Permanent slug-based access do leada przez `/transport/zapytanie/portal/{slug}`.
 * Pokrywa: mail wysłany po submit, portal renderuje dane, revoke, invalid slug.
 */
class TransportLeadPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_generates_access_slug_and_sends_email(): void
    {
        $this->mockGeocoder();
        Mail::fake();

        $this->post('/transport/zapytanie', [
            'customer_name' => 'Jan Kowalski',
            'customer_email' => 'jan@example.com',
            'pickup_address' => 'Warszawa',
            'dropoff_address' => 'Kraków',
            'preferred_date' => now()->addDays(5)->toDateString(),
            'horse_count' => 1,
            'terms' => '1',
        ]);

        $lead = TransportLead::first();
        $this->assertNotNull($lead->access_slug);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $lead->access_slug);
        $this->assertNull($lead->access_revoked_at);

        Mail::assertSent(TransportLeadAccessMail::class, function (TransportLeadAccessMail $mail) use ($lead) {
            return $mail->hasTo('jan@example.com')
                && $mail->lead->id === $lead->id
                && str_contains($mail->portalUrl, $lead->access_slug);
        });
    }

    public function test_portal_renders_lead_summary_and_offers(): void
    {
        $lead = TransportLead::create([
            'id' => (string) Str::ulid(),
            'access_slug' => (string) Str::uuid(),
            'mode' => 'broadcast',
            'originator_name' => 'Anna Nowak',
            'originator_email' => 'anna@example.com',
            'pickup_address' => 'Warszawa, Marymoncka 1',
            'pickup_lat' => 52.0,
            'pickup_lng' => 21.0,
            'pickup_voivodeship' => 'mazowieckie',
            'dropoff_address' => 'Kraków, Krakusa 1',
            'dropoff_lat' => 50.0,
            'dropoff_lng' => 19.9,
            'dropoff_voivodeship' => 'małopolskie',
            'preferred_date' => now()->addDays(7)->toDateString(),
            'horse_count' => 2,
            'notes' => 'Konie sportowe',
            'status' => 'open',
            'expires_at' => now()->addDays(14),
        ]);

        $transporter = $this->makeTransporterTenant('galoptrans');

        TransportLeadResponse::create([
            'id' => (string) Str::ulid(),
            'lead_id' => $lead->id,
            'transporter_tenant_id' => $transporter->id,
            'price_net' => 1000.00,
            'price_gross' => 1230.00,
            'currency' => 'PLN',
            'proposed_date' => now()->addDays(7)->toDateString(),
            'status' => 'pending',
        ]);

        $response = $this->get(route('public.transport.lead_portal', ['slug' => $lead->access_slug]));

        $response->assertOk();
        $response->assertSee('Warszawa, Marymoncka 1');
        $response->assertSee('Kraków, Krakusa 1');
        $response->assertSee('Konie sportowe');
        $response->assertSee($transporter->name);
        // Price formatted with non-breaking space thousands separator: "1 230,00 PLN"
        $response->assertSee('1 230,00');
    }

    public function test_portal_404s_on_unknown_slug(): void
    {
        $this->get('/transport/zapytanie/portal/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    }

    public function test_portal_404s_on_revoked_access(): void
    {
        $lead = TransportLead::create([
            'id' => (string) Str::ulid(),
            'access_slug' => (string) Str::uuid(),
            'access_revoked_at' => now(),
            'mode' => 'broadcast',
            'originator_email' => 'x@example.com',
            'pickup_address' => 'A',
            'pickup_lat' => 0,
            'pickup_lng' => 0,
            'pickup_voivodeship' => '',
            'dropoff_address' => 'B',
            'dropoff_lat' => 0,
            'dropoff_lng' => 0,
            'dropoff_voivodeship' => '',
            'preferred_date' => now()->addDay()->toDateString(),
            'horse_count' => 1,
            'status' => 'open',
            'expires_at' => now()->addDays(14),
        ]);

        $this->get(route('public.transport.lead_portal', ['slug' => $lead->access_slug]))
            ->assertNotFound();
    }

    public function test_portal_404s_on_malformed_slug(): void
    {
        $this->get('/transport/zapytanie/portal/not-a-uuid-format')
            ->assertNotFound();
    }

    private function makeTransporterTenant(string $slug): Tenant
    {
        return Tenant::create([
            'slug' => $slug,
            'name' => 'Firma '.$slug,
            'type' => TenantType::Transporter->value,
            'verification_status' => VerificationStatus::Verified->value,
            'verified_at' => now(),
            'db_name' => 't_'.$slug,
            'db_username' => 't_'.$slug,
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
                        'place_name' => 'ul. Marymoncka 1, Warszawa, Polska',
                        'center' => [21.0122, 52.2297],
                        'context' => [['id' => 'region.1', 'text' => 'Mazowieckie']],
                    ]],
                ])
                ->push([
                    'features' => [[
                        'place_name' => 'ul. Krakusa 1, Kraków, Polska',
                        'center' => [19.9362, 50.0413],
                        'context' => [['id' => 'region.2', 'text' => 'Małopolskie']],
                    ]],
                ]),
        ]);
    }
}
