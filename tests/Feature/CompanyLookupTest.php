<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Central\SystemSetting;
use App\Services\CompanyLookup\CeidgApiService;
use App\Services\CompanyLookup\CompanyLookupService;
use App\Services\CompanyLookup\GusApiService;
use App\Services\CompanyLookup\KrsApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompanyLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_nip_checksum_validation(): void
    {
        // 5260250274 jest poprawny (przykład z dokumentacji GUS)
        $this->assertTrue(CompanyLookupService::isValidNip('5260250274'));
        $this->assertTrue(CompanyLookupService::isValidNip('526-025-02-74'));   // myślniki ok
        $this->assertFalse(CompanyLookupService::isValidNip('5260250275'));     // zła suma
        $this->assertFalse(CompanyLookupService::isValidNip('123'));            // za krótki
        $this->assertFalse(CompanyLookupService::isValidNip('abcdefghij'));     // litery
    }

    public function test_krs_format_validation(): void
    {
        $this->assertTrue(KrsApiService::isValidKrs('0000123456'));
        $this->assertTrue(KrsApiService::isValidKrs('0000-123-456'));
        $this->assertFalse(KrsApiService::isValidKrs('123'));
        $this->assertFalse(KrsApiService::isValidKrs('00001234567'));
    }

    public function test_system_setting_secret_round_trip(): void
    {
        SystemSetting::setSecret('test.secret', 'super-secret-value');

        $this->assertSame('super-secret-value', SystemSetting::getSecret('test.secret'));

        // Raw stored value is wrapped + encrypted
        $raw = SystemSetting::getValue('test.secret');
        $this->assertIsArray($raw);
        $this->assertArrayHasKey('__crypt', $raw);
        $this->assertNotSame('super-secret-value', $raw['__crypt']);
    }

    public function test_gus_lookup_returns_null_when_not_configured(): void
    {
        // Brak SystemSetting 'gus.api_key' → null bez wywołania API
        $result = app(GusApiService::class)->findByNip('5260250274');
        $this->assertNull($result);
    }

    public function test_gus_lookup_parses_search_response_when_configured(): void
    {
        SystemSetting::setSecret('gus.api_key', 'test-key');
        SystemSetting::setValue('gus.env', 'test');

        $loginResponse = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">'
            .'<s:Body><ZalogujResult xmlns="http://CIS/BIR/PUBL/2014/07">SID-XYZ</ZalogujResult></s:Body>'
            .'</s:Envelope>';

        // Inline data block (XML escaped per GUS contract)
        $innerXml = '<root><dane><Regon>123456789</Regon><Nazwa>Stadnina Bucefał Sp. z o.o.</Nazwa>'
            .'<Ulica>ul. Kasztanowa</Ulica><NrNieruchomosci>7</NrNieruchomosci>'
            .'<KodPocztowy>00-001</KodPocztowy><Miejscowosc>Warszawa</Miejscowosc>'
            .'<Wojewodztwo>MAZOWIECKIE</Wojewodztwo><Typ>P</Typ></dane></root>';
        $searchResponse = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">'
            .'<s:Body>'
            .'<DaneSzukajPodmiotyResponse xmlns="http://CIS/BIR/PUBL/2014/07">'
            .'<DaneSzukajPodmiotyResult>'.htmlspecialchars($innerXml, ENT_XML1).'</DaneSzukajPodmiotyResult>'
            .'</DaneSzukajPodmiotyResponse>'
            .'</s:Body></s:Envelope>';

        $logoutResponse = '<?xml version="1.0"?><s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope"><s:Body/></s:Envelope>';

        Http::fakeSequence()
            ->push($loginResponse, 200)
            ->push($searchResponse, 200)
            ->push($logoutResponse, 200);

        $data = app(GusApiService::class)->findByNip('5260250274');

        $this->assertNotNull($data);
        $this->assertSame('123456789', $data['regon']);
        $this->assertSame('Stadnina Bucefał Sp. z o.o.', $data['name']);
        $this->assertSame('ul. Kasztanowa', $data['street']);
        $this->assertSame('7', $data['building']);
        $this->assertSame('00-001', $data['postal_code']);
        $this->assertSame('Warszawa', $data['city']);
    }

    public function test_gus_lookup_returns_null_on_invalid_nip_without_api_call(): void
    {
        SystemSetting::setSecret('gus.api_key', 'test-key');
        Http::fake();

        $this->assertNull(app(GusApiService::class)->findByNip('1234'));
        Http::assertNothingSent();
    }

    public function test_company_lookup_lookup_by_nip_returns_null_for_invalid_nip(): void
    {
        SystemSetting::setSecret('gus.api_key', 'test-key');
        Http::fake();

        $this->assertNull(app(CompanyLookupService::class)->lookupByNip('5260250275')); // zła suma
        Http::assertNothingSent();
    }

    public function test_krs_lookup_caches_response(): void
    {
        $payload = [
            'odpis' => [
                'dane' => [
                    'dzial1' => [
                        'danePodmiotu' => [
                            'nazwa' => 'Stajnia ABC',
                            'identyfikatory' => ['nip' => '5260250274', 'regon' => '123456789'],
                            'formaPrawna' => 'SPÓŁKA Z O.O.',
                        ],
                        'siedzibaIAdres' => [
                            'adres' => [
                                'ulica' => 'Kwiatowa',
                                'nrDomu' => '5',
                                'kodPocztowy' => '00-002',
                                'miejscowosc' => 'Kraków',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        Http::fake([
            'api-krs.ms.gov.pl/*' => Http::response($payload, 200),
        ]);

        $result = app(CompanyLookupService::class)->lookupByKrs('0000123456');

        $this->assertNotNull($result);
        $this->assertSame('Stajnia ABC', $result['name']);
        $this->assertSame('5260250274', $result['nip']);
        $this->assertSame('Kraków', $result['city']);
        $this->assertSame('Kwiatowa', $result['street']);
        $this->assertSame('5', $result['building']);
    }

    public function test_krs_lookup_returns_null_on_invalid_krs_without_api_call(): void
    {
        Http::fake();
        $this->assertNull(app(CompanyLookupService::class)->lookupByKrs('123'));
        Http::assertNothingSent();
    }

    public function test_krs_lookup_returns_null_on_404(): void
    {
        Http::fake([
            'api-krs.ms.gov.pl/*' => Http::response('Not Found', 404),
        ]);

        $this->assertNull(app(CompanyLookupService::class)->lookupByKrs('0000999999'));
    }

    public function test_ceidg_lookup_returns_null_when_not_configured(): void
    {
        $this->assertNull(app(CeidgApiService::class)->findByNip('5260250274'));
    }

    public function test_ceidg_lookup_parses_response_when_configured(): void
    {
        SystemSetting::setSecret('ceidg.api_token', 'jwt-token-here');

        Http::fake([
            'datastore.ceidg.gov.pl/*' => Http::response([
                'firma' => [[
                    'nazwa' => 'Jan Kowalski Transport JDG',
                    'status' => 'AKTYWNY',
                    'dataRozpoczecia' => '2020-01-15',
                    'adresDzialalnosci' => [
                        'ulica' => 'Polna',
                        'budynek' => '12',
                        'kod' => '00-001',
                        'miasto' => 'Warszawa',
                        'wojewodztwo' => 'MAZOWIECKIE',
                    ],
                    'telefon' => '+48 600 100 200',
                ]],
            ], 200),
        ]);

        $data = app(CeidgApiService::class)->findByNip('5260250274');

        $this->assertNotNull($data);
        $this->assertSame('Jan Kowalski Transport JDG', $data['name']);
        $this->assertSame('AKTYWNY', $data['status']);
        $this->assertSame('Polna', $data['street']);
        $this->assertSame('12', $data['building']);
        $this->assertSame('00-001', $data['postal_code']);
        $this->assertSame('Warszawa', $data['city']);
        $this->assertSame('+48 600 100 200', $data['phone']);
    }

    public function test_ceidg_lookup_returns_null_on_404(): void
    {
        SystemSetting::setSecret('ceidg.api_token', 'jwt-token-here');
        Http::fake([
            'datastore.ceidg.gov.pl/*' => Http::response('Not Found', 404),
        ]);

        $this->assertNull(app(CeidgApiService::class)->findByNip('5260250274'));
    }

    public function test_combined_lookup_merges_gus_and_ceidg_sources(): void
    {
        SystemSetting::setSecret('gus.api_key', 'gus-key');
        SystemSetting::setSecret('ceidg.api_token', 'ceidg-token');

        // GUS returns basic data, CEIDG returns enriched + phone
        $loginResponse = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">'
            .'<s:Body><ZalogujResult xmlns="http://CIS/BIR/PUBL/2014/07">SID-XYZ</ZalogujResult></s:Body>'
            .'</s:Envelope>';

        $innerXml = '<root><dane><Regon>123456789</Regon><Nazwa>Stary Nazwa Z GUS</Nazwa>'
            .'<Ulica>Kasztanowa</Ulica><NrNieruchomosci>7</NrNieruchomosci>'
            .'<KodPocztowy>00-001</KodPocztowy><Miejscowosc>Warszawa</Miejscowosc>'
            .'<Typ>F</Typ></dane></root>';
        $searchResponse = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">'
            .'<s:Body><DaneSzukajPodmiotyResponse xmlns="http://CIS/BIR/PUBL/2014/07">'
            .'<DaneSzukajPodmiotyResult>'.htmlspecialchars($innerXml, ENT_XML1).'</DaneSzukajPodmiotyResult>'
            .'</DaneSzukajPodmiotyResponse></s:Body></s:Envelope>';
        $logoutResponse = '<?xml version="1.0"?><s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope"><s:Body/></s:Envelope>';

        Http::fake([
            'wyszukiwarkaregon*' => Http::sequence()
                ->push($loginResponse, 200)
                ->push($searchResponse, 200)
                ->push($logoutResponse, 200),
            'datastore.ceidg.gov.pl/*' => Http::response([
                'firma' => [[
                    'nazwa' => 'Nowa Nazwa Z CEIDG',
                    'telefon' => '+48 600 100 200',
                    'adresDzialalnosci' => [
                        'ulica' => 'Polna',
                        'budynek' => '12',
                        'kod' => '00-002',
                        'miasto' => 'Kraków',
                    ],
                ]],
            ], 200),
        ]);

        $data = app(CompanyLookupService::class)->lookupByNip('5260250274');

        $this->assertNotNull($data);
        // CEIDG override'uje pola które ma uzupełnione
        $this->assertSame('Nowa Nazwa Z CEIDG', $data['name']);
        $this->assertSame('Polna', $data['street']);
        $this->assertSame('Kraków', $data['city']);
        $this->assertSame('+48 600 100 200', $data['phone']);
        // GUS regon zachowany (CEIDG go nie zwraca)
        $this->assertSame('123456789', $data['regon']);
        // sources lista zawiera oba
        $this->assertContains('gus', $data['sources']);
        $this->assertContains('ceidg', $data['sources']);
    }

    public function test_combined_lookup_falls_back_to_gus_when_ceidg_not_configured(): void
    {
        SystemSetting::setSecret('gus.api_key', 'gus-key');
        // brak CEIDG token

        $loginResponse = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">'
            .'<s:Body><ZalogujResult xmlns="http://CIS/BIR/PUBL/2014/07">SID-XYZ</ZalogujResult></s:Body>'
            .'</s:Envelope>';

        $innerXml = '<root><dane><Regon>123456789</Regon><Nazwa>GUS Tylko</Nazwa>'
            .'<Miejscowosc>Warszawa</Miejscowosc><Typ>F</Typ></dane></root>';
        $searchResponse = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">'
            .'<s:Body><DaneSzukajPodmiotyResponse xmlns="http://CIS/BIR/PUBL/2014/07">'
            .'<DaneSzukajPodmiotyResult>'.htmlspecialchars($innerXml, ENT_XML1).'</DaneSzukajPodmiotyResult>'
            .'</DaneSzukajPodmiotyResponse></s:Body></s:Envelope>';
        $logoutResponse = '<?xml version="1.0"?><s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope"><s:Body/></s:Envelope>';

        Http::fakeSequence()
            ->push($loginResponse, 200)
            ->push($searchResponse, 200)
            ->push($logoutResponse, 200);

        $data = app(CompanyLookupService::class)->lookupByNip('5260250274');

        $this->assertNotNull($data);
        $this->assertSame('GUS Tylko', $data['name']);
        $this->assertSame(['gus'], $data['sources']);
    }
}
