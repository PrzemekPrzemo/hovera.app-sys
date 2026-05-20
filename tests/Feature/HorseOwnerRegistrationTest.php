<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Tenants\CreateTenant;
use App\Enums\TenantType;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Mockery\MockInterface;
use Tests\TestCase;

class HorseOwnerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_form(): void
    {
        $response = $this->get('/register/horse-owner');

        $response->assertOk();
        $response->assertSee('Załóż konto właściciela konia', escape: false);
    }

    public function test_show_with_invite_query_shows_banner(): void
    {
        $response = $this->get('/register/horse-owner?stable=01ABC&token=xyz&email=jan@example.com');

        $response->assertOk();
        $response->assertSee('jan@example.com');
        // Banner with invitation context
        $response->assertSee('stajni', escape: false);
    }

    public function test_submit_validates_required_fields(): void
    {
        $response = $this->post('/register/horse-owner', []);

        $response->assertSessionHasErrors(['owner_name', 'owner_email', 'terms']);
    }

    public function test_submit_validates_email_format(): void
    {
        $response = $this->post('/register/horse-owner', [
            'owner_name' => 'Jan Kowalski',
            'owner_email' => 'not-an-email',
            'terms' => '1',
        ]);

        $response->assertSessionHasErrors(['owner_email']);
    }

    public function test_submit_creates_horse_owner_tenant_and_redirects_to_thanks(): void
    {
        Plan::create([
            'code' => 'owner_free',
            'audience' => 'horse_owner',
            'name' => 'Owner Free',
            'currency' => 'PLN',
            'price_monthly_cents' => 0,
            'price_yearly_cents' => 0,
            'limits' => ['max_horses' => 10],
            'features' => [],
            'sort_order' => 100,
            'is_active' => true,
            'is_public' => true,
        ]);

        // Mock CreateTenant — nie odpalamy faktycznego provisioning'u w teście
        // (wymagałby MySQL provisioner connection).
        $tenant = Tenant::create([
            'slug' => 'jan-test123',
            'name' => 'Jan Kowalski',
            'type' => TenantType::HorseOwner,
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_name' => 'hovera_t_jan_test123',
            'db_username' => 'hovera_t_jan_test123',
            'db_password_encrypted' => Crypt::encryptString('secret'),
            'status' => 'active',
        ]);

        $this->mock(CreateTenant::class, function (MockInterface $m) use ($tenant) {
            $m->shouldReceive('execute')->andReturn($tenant);
        });

        $response = $this->post('/register/horse-owner', [
            'owner_name' => 'Jan Kowalski',
            'owner_email' => 'jan@example.com',
            'owner_phone' => '+48 123 456 789',
            'terms' => '1',
        ]);

        $response->assertRedirect(route('register.horse-owner.thanks', ['slug' => 'jan-test123']));
    }

    public function test_thanks_page_displays_next_steps(): void
    {
        $response = $this->get('/register/horse-owner/dziekujemy/test-slug');

        $response->assertOk();
        $response->assertSee('sprawdź email', escape: false);
        $response->assertSee('/owner/login');
    }
}
