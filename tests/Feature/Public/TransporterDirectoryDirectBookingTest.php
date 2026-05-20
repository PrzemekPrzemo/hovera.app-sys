<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Direct booking CTA na kartach katalogu `/przewoznicy`.
 *
 * Klient klika "Zamów transport" na karcie → ląduje na
 * /transport/zapytanie?transporter={slug} → lead idzie tylko do tego
 * jednego przewoźnika (mode=direct). Mechanizm direct mode istnieje już
 * w `TransportInquiryController` od dawna; ten PR dorzuca tylko widoczny
 * CTA który ten flow uruchamia.
 */
class TransporterDirectoryDirectBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_card_links_to_direct_inquiry_with_transporter_slug(): void
    {
        $tenant = $this->makeVerifiedTransporter('test-carrier');

        $response = $this->get('/przewoznicy');

        $response->assertOk();
        $response->assertSee(
            'href="'.route('public.transport.inquiry', ['transporter' => 'test-carrier']).'"',
            escape: false,
        );
        $response->assertSeeText('Zamów transport');
    }

    public function test_directory_card_still_links_to_profile(): void
    {
        $this->makeVerifiedTransporter('test-carrier-2');

        $response = $this->get('/przewoznicy');

        $response->assertOk();
        // Profile link nadal obecny obok Order CTA
        $response->assertSee('href="'.route('public.transporter', ['slug' => 'test-carrier-2']).'"', escape: false);
    }

    public function test_inquiry_form_pre_selects_targeted_transporter(): void
    {
        $tenant = $this->makeVerifiedTransporter('test-carrier-3');

        $response = $this->get('/transport/zapytanie?transporter=test-carrier-3');

        $response->assertOk();
        // Form widzi targeted transporter (hidden input z slug'iem)
        $response->assertSee('value="test-carrier-3"', escape: false);
    }

    private function makeVerifiedTransporter(string $slug): Tenant
    {
        return Tenant::create([
            'slug' => $slug,
            'name' => 'Test Carrier '.$slug,
            'type' => TenantType::Transporter,
            'verification_status' => VerificationStatus::Verified,
            'status' => 'active',
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_name' => 'hovera_t_'.str_replace('-', '_', $slug),
            'db_username' => 'hovera_t_'.str_replace('-', '_', $slug),
            'db_password_encrypted' => Crypt::encryptString('x'),
        ]);
    }
}
