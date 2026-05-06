<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Tenants\CreateTenant;
use App\Models\Central\Tenant;
use App\Tenancy\Provisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Tests\TestCase;

class CreateTenantActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Stub the Provisioner so we don't actually CREATE DATABASE in tests.
        $this->mock(Provisioner::class, function (MockInterface $m) {
            $m->shouldReceive('makeIdentifiers')->andReturn([
                'db_name' => 'hovera_t_acme',
                'db_user' => 'hovera_t_acme',
            ]);
            $m->shouldReceive('generatePassword')->andReturn('PASSWORD123456789');
            $m->shouldReceive('provision')->andReturnNull();
            $m->shouldReceive('destroy')->andReturnNull();
        });
    }

    public function test_creates_tenant_row_and_persists_encrypted_password(): void
    {
        /** @var CreateTenant $action */
        $action = $this->app->make(CreateTenant::class);

        $tenant = $action->execute([
            'slug' => 'acme',
            'name' => 'Acme Stable',
        ]);

        $this->assertSame('acme', $tenant->slug);
        $this->assertSame('Acme Stable', $tenant->name);
        $this->assertSame('hovera_t_acme', $tenant->db_name);
        $this->assertSame('trialing', $tenant->status);

        $row = Tenant::where('slug', 'acme')->first();
        $this->assertNotNull($row->db_password_encrypted);
        $this->assertSame('PASSWORD123456789', Crypt::decryptString($row->db_password_encrypted));
    }

    public function test_rejects_invalid_slug(): void
    {
        /** @var CreateTenant $action */
        $action = $this->app->make(CreateTenant::class);

        $this->expectException(ValidationException::class);

        $action->execute([
            'slug' => 'A_BAD_SLUG!',
            'name' => 'No good',
        ]);
    }

    public function test_rejects_duplicate_slug(): void
    {
        /** @var CreateTenant $action */
        $action = $this->app->make(CreateTenant::class);

        $action->execute(['slug' => 'acme', 'name' => 'First']);

        $this->expectException(ValidationException::class);
        $action->execute(['slug' => 'acme', 'name' => 'Second']);
    }
}
