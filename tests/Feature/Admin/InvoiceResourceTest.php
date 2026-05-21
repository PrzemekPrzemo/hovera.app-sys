<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Central\Invoice;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('hovera.admin.require_2fa', false);
    }

    public function test_master_admin_can_list_invoices(): void
    {
        $admin = $this->makeAdmin();
        $tenant = $this->makeTenant();
        Invoice::create([
            'tenant_id' => $tenant->id,
            'number' => 'HVR/2026/05/0001',
            'kind' => 'regular',
            'plan_code' => 'stable',
            'period' => 'monthly',
            'currency' => 'PLN',
            'amount_cents' => 20244,
            'vat_cents' => 4656,
            'total_cents' => 24900,
            'vat_rate' => 23,
            'status' => 'open',
            'issued_at' => now(),
            'due_at' => now()->addDays(14),
        ]);

        $this->actingAs($admin)
            ->get('/admin/invoices')
            ->assertOk()
            ->assertSee('HVR/2026/05/0001');
    }

    public function test_non_admin_cannot_access_invoices(): void
    {
        $user = User::create([
            'email' => 'user@example.com',
            'name' => 'User',
            'password' => bcrypt('secret123'),
            'is_master_admin' => false,
        ]);

        $this->actingAs($user)
            ->get('/admin/invoices')
            ->assertRedirect();
    }

    private function makeAdmin(): User
    {
        return User::create([
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret123'),
            'is_master_admin' => true,
        ]);
    }

    private function makeTenant(): Tenant
    {
        $t = new Tenant([
            'slug' => 'acme',
            'name' => 'Acme',
            'db_name' => 'hovera_t_acme',
            'db_username' => 'hovera_t_acme',
            'status' => 'active',
        ]);
        $t->db_password = 'x';
        $t->save();

        return $t;
    }
}
