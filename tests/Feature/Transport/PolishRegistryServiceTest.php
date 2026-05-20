<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Customers\Exceptions\CompanyLookupException;
use App\Domain\Customers\PolishRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PolishRegistryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_nip_lookup_parses_mf_response(): void
    {
        Http::fake([
            'wl-api.mf.gov.pl/api/search/nip/*' => Http::response([
                'result' => [
                    'subject' => [
                        'name' => 'PRZEDSIĘBIORSTWO TRANSPORTOWE KOWALSKI SP. Z O.O.',
                        'nip' => '1234567890',
                        'regon' => '123456789',
                        'krs' => '0000123456',
                        'workingAddress' => 'ul. Marszałkowska 1, 00-001 Warszawa',
                        'statusVat' => 'Czynny',
                    ],
                ],
            ]),
        ]);

        $result = app(PolishRegistryService::class)->lookupByNip('1234567890');

        $this->assertSame('mf', $result->source);
        $this->assertStringContainsString('KOWALSKI', $result->name);
        $this->assertSame('1234567890', $result->taxId);
        $this->assertSame('123456789', $result->regon);
        $this->assertSame('0000123456', $result->krsNumber);
        $this->assertSame('Czynny', $result->status);
        $this->assertStringContainsString('Marszałkowska', (string) $result->address);
    }

    public function test_nip_lookup_throws_on_invalid_format(): void
    {
        $this->expectException(CompanyLookupException::class);
        $this->expectExceptionMessage('NIP musi mieć dokładnie 10 cyfr');

        app(PolishRegistryService::class)->lookupByNip('123');
    }

    public function test_nip_normalizes_spaces_and_dashes(): void
    {
        Http::fake([
            'wl-api.mf.gov.pl/api/search/nip/*' => Http::response([
                'result' => ['subject' => ['name' => 'Test', 'nip' => '1234567890']],
            ]),
        ]);

        // "123-456-78-90" → znormalizowane do "1234567890"
        $result = app(PolishRegistryService::class)->lookupByNip('123-456-78-90');
        $this->assertSame('1234567890', $result->taxId);
    }

    public function test_nip_lookup_throws_when_subject_missing(): void
    {
        Http::fake([
            'wl-api.mf.gov.pl/api/search/nip/*' => Http::response(['result' => ['subject' => null]]),
        ]);

        $this->expectException(CompanyLookupException::class);
        app(PolishRegistryService::class)->lookupByNip('1234567890');
    }

    public function test_nip_lookup_throws_on_api_error(): void
    {
        Http::fake([
            'wl-api.mf.gov.pl/api/search/nip/*' => Http::response(['error' => 'rate limit'], 429),
        ]);

        $this->expectException(CompanyLookupException::class);
        app(PolishRegistryService::class)->lookupByNip('1234567890');
    }

    public function test_krs_lookup_parses_response_with_nested_address(): void
    {
        Http::fake([
            'api-krs.ms.gov.pl/api/krs/*' => Http::response([
                'odpis' => ['dane' => ['dzial1' => [
                    'danePodmiotu' => ['nazwa' => 'STAJNIA TRANSPORT S.A.'],
                    'identyfikatory' => ['nip' => '1234567890', 'regon' => '123456789'],
                    'siedzibaIAdres' => ['adres' => [
                        'ulica' => 'Marszałkowska',
                        'nrDomu' => '1',
                        'nrLokalu' => '2A',
                        'kodPocztowy' => '00-001',
                        'miejscowosc' => 'Warszawa',
                    ]],
                ]]],
            ]),
        ]);

        $result = app(PolishRegistryService::class)->lookupByKrs('0000123456');

        $this->assertSame('krs', $result->source);
        $this->assertSame('STAJNIA TRANSPORT S.A.', $result->name);
        $this->assertSame('1234567890', $result->taxId);
        $this->assertSame('0000123456', $result->krsNumber);
        $this->assertStringContainsString('Marszałkowska 1/2A', (string) $result->address);
        $this->assertStringContainsString('00-001 Warszawa', (string) $result->address);
    }

    public function test_krs_lookup_throws_on_invalid_format(): void
    {
        $this->expectException(CompanyLookupException::class);
        app(PolishRegistryService::class)->lookupByKrs('123');
    }
}
