<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Enums\QuoteStatus;
use App\Enums\TenantType;
use App\Filament\Transport\Resources\QuoteResource;
use App\Models\Central\Tenant;
use App\Models\Tenant\Quote;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * WhatsApp share dla ofert transportowych — `QuoteResource::buildWhatsAppShareUrl()`
 * generuje `https://wa.me/{phone}?text=...` link ktory user klika i ladnie
 * otwiera WhatsApp (telefon / desktop / web) z pre-filled draft message.
 *
 * Pokrywa:
 *  - PL national 9-digit phone → prefix 48
 *  - PL with leading 0 (10 digits) → strip 0, prefix 48
 *  - International (+...) → kept as-is bez +
 *  - Spaces / dashes / parentheses → stripped
 *  - URL zawiera link do public landing + cene + nazwe firmy
 */
class QuoteShareWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalizes_pl_9_digit_phone(): void
    {
        $url = $this->buildShareUrl('501234567');

        $this->assertStringStartsWith('https://wa.me/48501234567?text=', $url);
    }

    public function test_normalizes_pl_with_leading_zero(): void
    {
        $url = $this->buildShareUrl('0501234567');

        $this->assertStringStartsWith('https://wa.me/48501234567?text=', $url);
    }

    public function test_strips_spaces_dashes_and_parentheses(): void
    {
        $url = $this->buildShareUrl('+48 (501) 234-567');

        // Po stripie znakow specjalnych zostaje 48501234567 — '+' zniknal.
        $this->assertStringStartsWith('https://wa.me/48501234567?text=', $url);
    }

    public function test_strips_double_zero_international_prefix(): void
    {
        // '0048...' to alternatywny prefix miedzynarodowy (00 zamiast +).
        $url = $this->buildShareUrl('0048501234567');

        $this->assertStringStartsWith('https://wa.me/48501234567?text=', $url);
    }

    public function test_url_contains_landing_link_and_price(): void
    {
        $url = $this->buildShareUrl('501234567', [
            'gross_total' => '1234.56',
            'currency' => 'EUR',
        ]);

        $decoded = rawurldecode(substr($url, strpos($url, '?text=') + 6));
        $this->assertStringContainsString('1 234,56 EUR', $decoded);
        $this->assertStringContainsString('/transport/quote/', $decoded);
    }

    public function test_url_contains_pickup_dropoff_when_available(): void
    {
        $url = $this->buildShareUrl('501234567', [
            'pickup_address' => 'Warszawa',
            'dropoff_address' => 'Kraków',
        ]);

        $decoded = rawurldecode(substr($url, strpos($url, '?text=') + 6));
        $this->assertStringContainsString('Warszawa', $decoded);
        $this->assertStringContainsString('Kraków', $decoded);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function buildShareUrl(string $phone, array $overrides = []): string
    {
        $tenant = Tenant::create([
            'slug' => 'firma-'.uniqid(),
            'name' => 'Firma Transport',
            'legal_name' => 'Firma Transport sp. z o.o.',
            'type' => TenantType::Transporter,
            'db_name' => 'firma_'.uniqid(),
            'db_username' => 'firma_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);

        $this->mock(TenantManager::class, function ($m) use ($tenant) {
            $m->shouldReceive('current')->andReturn($tenant);
            $m->shouldReceive('tenantOrFail')->andReturn($tenant);
        });

        $quote = new Quote(array_merge([
            'id' => 'quote-id',
            'number' => 'OF/2026/05/001',
            'status' => QuoteStatus::Sent,
            'customer_name' => 'Jan Kowalski',
            'customer_phone' => $phone,
            'pickup_address' => 'Warszawa',
            'dropoff_address' => 'Kraków',
            'preferred_date' => '2026-06-15',
            'currency' => 'PLN',
            'gross_total' => '1000.00',
            'accept_token' => str_repeat('a', 48),
        ], $overrides));

        return QuoteResource::buildWhatsAppShareUrl($quote);
    }
}
