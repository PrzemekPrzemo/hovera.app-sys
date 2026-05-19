<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Console\Commands\ExpireFeaturedTenants;
use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Daily cron `transport:expire-featured` — flip is_featured=false dla
 * tenantów z featured_until < NOW(). Patrz docs/TRANSPORT.md §16.
 */
class ExpireFeaturedTenantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_expires_featured_for_past_until_timestamp(): void
    {
        $expired = $this->makeFeatured(featuredUntil: now()->subHour());
        $active = $this->makeFeatured(featuredUntil: now()->addDays(10));

        $this->artisan(ExpireFeaturedTenants::class)
            ->assertExitCode(0)
            ->expectsOutputToContain('Expired featured for 1 tenant(s)');

        $expired->refresh();
        $active->refresh();
        $this->assertFalse($expired->is_featured);
        $this->assertTrue($active->is_featured);
    }

    public function test_legacy_permanent_featured_not_touched(): void
    {
        // featured_until=NULL == legacy permanent featured (master admin manually toggled)
        $permanent = $this->makeFeatured(featuredUntil: null);

        $this->artisan(ExpireFeaturedTenants::class)->assertExitCode(0);

        $permanent->refresh();
        $this->assertTrue($permanent->is_featured);
    }

    public function test_no_expired_outputs_friendly_message(): void
    {
        $this->makeFeatured(featuredUntil: now()->addDay());

        $this->artisan(ExpireFeaturedTenants::class)
            ->assertExitCode(0)
            ->expectsOutput('No expired featured tenants.');
    }

    private function makeFeatured(?Carbon $featuredUntil): Tenant
    {
        $u = uniqid();
        $tenant = Tenant::create([
            'slug' => 't-'.$u,
            'name' => 'Firma '.$u,
            'type' => TenantType::Transporter,
            'verification_status' => VerificationStatus::Verified,
            'db_name' => 't_'.$u,
            'db_username' => 't_'.$u,
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);

        $tenant->forceFill([
            'is_featured' => true,
            'featured_at' => now()->subDays(2),
            'featured_until' => $featuredUntil,
        ])->save();

        return $tenant;
    }
}
