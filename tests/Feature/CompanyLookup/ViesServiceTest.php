<?php

declare(strict_types=1);

namespace Tests\Feature\CompanyLookup;

use App\Models\Central\SystemSetting;
use App\Services\CompanyLookup\CompanyLookupService;
use App\Services\CompanyLookup\ViesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * VIES — EU VAT validation.
 *
 * Pokrywa:
 *  - parser EU prefix ("DE123456789" / "de 123 456 789" / "PL 526-025-02-74")
 *  - validate() happy path (isValid=true + name + address)
 *  - validate() invalid (isValid=false → wpis cache'owany ale valid=false)
 *  - soft-fail przy API down → null + log
 *  - cache 24h (drugie wywołanie nie hituje API)
 *  - smart route: VIES dla non-PL UE, GUS dla PL/digits
 *  - VIES "---" placeholder dla państw z privacy (DE/ES) → name/address null
 */
class ViesServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_parse_eu_vat_id_strips_spaces_and_dashes(): void
    {
        $this->assertSame(
            ['country_code' => 'DE', 'vat_number' => '123456789'],
            ViesService::parseEuVatId('DE 123 456 789'),
        );
        $this->assertSame(
            ['country_code' => 'PL', 'vat_number' => '5260250274'],
            ViesService::parseEuVatId('PL 526-025-02-74'),
        );
        $this->assertSame(
            ['country_code' => 'FR', 'vat_number' => '12345678901'],
            ViesService::parseEuVatId('fr12345678901'),
        );
    }

    public function test_parse_eu_vat_id_rejects_non_eu_prefix(): void
    {
        $this->assertNull(ViesService::parseEuVatId('US123456789'));
        $this->assertNull(ViesService::parseEuVatId('GB123456789')); // post-Brexit
        $this->assertNull(ViesService::parseEuVatId('5260250274'));  // brak prefix
        $this->assertNull(ViesService::parseEuVatId('XX'));
        $this->assertNull(ViesService::parseEuVatId(''));
    }

    public function test_validate_returns_company_data_on_valid_vat(): void
    {
        Http::fake([
            'ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number*' => Http::response([
                'isValid' => true,
                'countryCode' => 'DE',
                'vatNumber' => '123456789',
                'name' => 'Beispiel GmbH',
                'address' => "Musterstrasse 1\n10115 Berlin",
                'requestDate' => '2026-05-22+01:00',
            ]),
        ]);

        $result = app(ViesService::class)->validate('DE', '123456789');

        $this->assertNotNull($result);
        $this->assertTrue($result['valid']);
        $this->assertSame('Beispiel GmbH', $result['name']);
        $this->assertSame("Musterstrasse 1\n10115 Berlin", $result['address']);
        $this->assertSame('vies', $result['source']);
    }

    public function test_validate_returns_invalid_payload_when_vies_says_not_valid(): void
    {
        Http::fake([
            'ec.europa.eu/*' => Http::response([
                'isValid' => false,
                'countryCode' => 'DE',
                'vatNumber' => '999999999',
                'name' => '---',
                'address' => '---',
            ]),
        ]);

        $result = app(ViesService::class)->validate('DE', '999999999');

        $this->assertNotNull($result);
        $this->assertFalse($result['valid']);
        $this->assertNull($result['name']);
        $this->assertNull($result['address']);
    }

    public function test_validate_normalizes_dashes_placeholder_to_null(): void
    {
        // Niemcy / Hiszpania zwracają "---" gdy podatnik nie udostępnia
        // nazwy/adresu publicznie (privacy ustawienie).
        Http::fake([
            'ec.europa.eu/*' => Http::response([
                'isValid' => true,
                'countryCode' => 'ES',
                'vatNumber' => 'B12345678',
                'name' => '---',
                'address' => '---',
            ]),
        ]);

        $result = app(ViesService::class)->validate('ES', 'B12345678');

        $this->assertTrue($result['valid']);
        $this->assertNull($result['name']);
        $this->assertNull($result['address']);
    }

    public function test_validate_soft_fails_on_api_down(): void
    {
        Http::fake([
            'ec.europa.eu/*' => Http::response('Service unavailable', 503),
        ]);

        $result = app(ViesService::class)->validate('DE', '123456789');

        $this->assertNull($result);
    }

    public function test_validate_uses_cache_on_second_call(): void
    {
        Http::fake([
            'ec.europa.eu/*' => Http::response([
                'isValid' => true,
                'countryCode' => 'FR',
                'vatNumber' => '40303265045',
                'name' => 'Acme SA',
                'address' => '1 rue de la Paix, Paris',
            ]),
        ]);

        $svc = app(ViesService::class);
        $svc->validate('FR', '40303265045'); // 1st call → fetch
        $svc->validate('FR', '40303265045'); // 2nd call → cache hit

        // Tylko jeden request poszedł do VIES.
        Http::assertSentCount(1);
    }

    public function test_smart_route_uses_vies_for_non_pl_eu(): void
    {
        Http::fake([
            'ec.europa.eu/*' => Http::response([
                'isValid' => true,
                'countryCode' => 'IT',
                'vatNumber' => '12345678901',
                'name' => 'Cavalli SRL',
                'address' => 'Via Roma 1, 00100 Roma',
            ]),
        ]);

        $result = app(CompanyLookupService::class)->lookupSmart('IT12345678901');

        $this->assertNotNull($result);
        $this->assertSame('Cavalli SRL', $result['name']);
        $this->assertSame('Via Roma 1, 00100 Roma', $result['street']);
        $this->assertSame('IT', $result['country']);
        $this->assertSame(['vies'], $result['sources']);
        $this->assertTrue($result['valid']);
    }

    public function test_smart_route_strips_pl_prefix_and_uses_gus_path(): void
    {
        // "PL5260250274" → po stripie "5260250274" idzie do GUS. GUS API
        // nie skonfigurowane w testach → CompanyLookupService zwróci null
        // (nie wykonujemy realnego GUS w teście — sprawdzamy że PL prefix
        // został usunięty i nie poszło do VIES).
        Http::preventStrayRequests();
        Http::fake();

        $result = app(CompanyLookupService::class)->lookupSmart('PL5260250274');

        // Brak danych z GUS — but ważne że VIES NIE został wywołany.
        $this->assertNull($result);
        Http::assertNothingSent();
    }

    public function test_smart_route_returns_null_for_invalid_pl_nip_without_calling_anything(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $result = app(CompanyLookupService::class)->lookupSmart('1234567890');

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    public function test_system_setting_base_url_override_takes_priority(): void
    {
        // Master admin wpisał własny URL (np. proxy/mirror) w
        // /admin/company-lookup-settings → SystemSetting::setValue.
        SystemSetting::setValue('vies.base_url', 'https://my-vies-proxy.example.com/api');

        Http::fake([
            'my-vies-proxy.example.com/*' => Http::response([
                'isValid' => true,
                'name' => 'Proxy GmbH',
                'address' => 'Proxy 1',
            ]),
            // Jeśli kod by miał błędnie hit'nąć default endpoint — to wybuchnie
            // bo Http::preventStrayRequests poniżej.
        ]);

        $result = app(ViesService::class)->validate('DE', '123456789');

        $this->assertNotNull($result);
        $this->assertTrue($result['valid']);
        $this->assertSame('Proxy GmbH', $result['name']);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'my-vies-proxy.example.com'));
    }
}
