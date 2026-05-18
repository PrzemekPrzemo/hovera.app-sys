<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Central\TransportFavorite;
use App\Models\Central\TransportLead;
use App\Models\Central\TransportLeadDispatch;
use App\Models\Central\TransportLeadResponse;
use App\Models\Central\TransportServiceArea;
use App\Models\Central\TransporterProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransportMarketplaceTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_with_dispatch_and_responses_roundtrips(): void
    {
        $lead = TransportLead::create([
            'mode' => 'direct',
            'targeted_transporter_ids' => [(string) Str::ulid(), (string) Str::ulid()],
            'pickup_address' => 'Warszawa, Marymoncka 1',
            'pickup_lat' => 52.2818,
            'pickup_lng' => 20.9921,
            'pickup_voivodeship' => 'mazowieckie',
            'dropoff_address' => 'Kraków, Krakusa 1',
            'dropoff_lat' => 50.0413,
            'dropoff_lng' => 19.9362,
            'dropoff_voivodeship' => 'małopolskie',
            'preferred_date' => '2026-06-15',
            'horse_count' => 2,
            'horses' => [
                ['name' => 'Bucefał', 'height_cm' => 168],
                ['name' => 'Pegaz', 'height_cm' => 162],
            ],
            'expires_at' => now()->addDays(14),
        ]);

        $transporterId = (string) Str::ulid();
        TransportLeadDispatch::create([
            'lead_id' => $lead->id,
            'transporter_tenant_id' => $transporterId,
            'notified_email' => true,
            'notified_at' => now(),
        ]);
        TransportLeadResponse::create([
            'lead_id' => $lead->id,
            'transporter_tenant_id' => $transporterId,
            'price_net' => 1400.00,
            'price_gross' => 1722.00,
            'currency' => 'PLN',
            'distance_km' => 295.50,
            'proposed_date' => '2026-06-15',
            'terms' => 'Płatność 50% zaliczki + 50% po dostawie.',
        ]);

        $fresh = $lead->fresh(['dispatches', 'responses']);

        $this->assertCount(2, $fresh->targeted_transporter_ids);
        $this->assertCount(2, $fresh->horses);
        $this->assertSame('Bucefał', $fresh->horses[0]['name']);
        $this->assertCount(1, $fresh->dispatches);
        $this->assertCount(1, $fresh->responses);
        $this->assertSame('1400.00', $fresh->responses->first()->price_net);
        $this->assertSame('open', $fresh->status);
    }

    public function test_lead_response_unique_per_lead_and_transporter(): void
    {
        $lead = TransportLead::create([
            'mode' => 'broadcast',
            'pickup_address' => 'a',
            'pickup_lat' => 0, 'pickup_lng' => 0, 'pickup_voivodeship' => 'mazowieckie',
            'dropoff_address' => 'b',
            'dropoff_lat' => 0, 'dropoff_lng' => 0, 'dropoff_voivodeship' => 'śląskie',
            'preferred_date' => '2026-07-01',
            'expires_at' => now()->addDays(14),
        ]);
        $transporterId = (string) Str::ulid();

        TransportLeadResponse::create([
            'lead_id' => $lead->id,
            'transporter_tenant_id' => $transporterId,
            'price_net' => 1000,
            'price_gross' => 1230,
            'proposed_date' => '2026-07-01',
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        TransportLeadResponse::create([
            'lead_id' => $lead->id,
            'transporter_tenant_id' => $transporterId,
            'price_net' => 999,
            'price_gross' => 1228,
            'proposed_date' => '2026-07-01',
        ]);
    }

    public function test_service_area_is_unique_per_transporter_voivodeship(): void
    {
        $tid = (string) Str::ulid();

        TransportServiceArea::create([
            'transporter_tenant_id' => $tid,
            'voivodeship' => 'mazowieckie',
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        TransportServiceArea::create([
            'transporter_tenant_id' => $tid,
            'voivodeship' => 'mazowieckie',
        ]);
    }

    public function test_favorite_can_be_created_for_stable_or_user(): void
    {
        $stableId = (string) Str::ulid();
        $userId = (string) Str::ulid();
        $transporterA = (string) Str::ulid();
        $transporterB = (string) Str::ulid();

        TransportFavorite::create([
            'stable_tenant_id' => $stableId,
            'transporter_tenant_id' => $transporterA,
        ]);
        TransportFavorite::create([
            'user_id' => $userId,
            'transporter_tenant_id' => $transporterB,
        ]);

        $this->assertSame(2, TransportFavorite::count());
    }

    public function test_transporter_profile_slug_must_be_unique(): void
    {
        $tenantA = (string) Str::ulid();
        $tenantB = (string) Str::ulid();

        TransporterProfile::create([
            'tenant_id' => $tenantA,
            'slug' => 'kowalscy-transport',
            'display_name' => 'Kowalscy Transport',
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        TransporterProfile::create([
            'tenant_id' => $tenantB,
            'slug' => 'kowalscy-transport',
            'display_name' => 'Kowalscy Inny',
        ]);
    }

    public function test_transporter_profile_jsons_round_trip(): void
    {
        $profile = TransporterProfile::create([
            'tenant_id' => (string) Str::ulid(),
            'slug' => 'kowalscy-'.uniqid(),
            'display_name' => 'Kowalscy',
            'social_links' => [
                'facebook' => 'https://fb.com/kowalscy',
                'instagram' => 'https://ig.com/kowalscy',
            ],
            'seo' => [
                'meta_title' => 'Kowalscy Transport',
                'meta_description' => 'Profesjonalny transport koni.',
            ],
        ]);

        $fresh = $profile->fresh();
        $this->assertSame('https://fb.com/kowalscy', $fresh->social_links['facebook']);
        $this->assertSame('Profesjonalny transport koni.', $fresh->seo['meta_description']);
        $this->assertFalse($fresh->is_published);
    }
}
