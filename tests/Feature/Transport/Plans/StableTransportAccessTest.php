<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Plans;

use App\Enums\TenantType;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Marketing spec: stables dostają moduł transport BEZPŁATNIE w ramach planu
 * Hovery (z wyjątkiem `free`). Transporterzy potrzebują własnego planu
 * transport_*. Patrz Tenant::canUseTransport().
 */
class StableTransportAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(TenantType $type, ?string $planCode = null, string $status = 'active'): Tenant
    {
        $plan = $planCode !== null
            ? Plan::firstOrCreate(['code' => $planCode], [
                'audience' => $type->value,
                'name' => ucfirst($planCode),
                'currency' => 'PLN',
            ])
            : null;

        $tenant = new Tenant([
            'slug' => 'test-'.uniqid(),
            'name' => 'T',
            'type' => $type,
            'db_name' => 'test_'.uniqid(),
            'db_username' => 'test_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => $status,
            'plan_id' => $plan?->id,
        ]);
        $tenant->save();
        if ($plan) {
            $tenant->setRelation('plan', $plan);
        }

        return $tenant;
    }

    public function test_stable_on_free_plan_cannot_use_transport(): void
    {
        $tenant = $this->makeTenant(TenantType::Stable, 'free');
        $this->assertFalse($tenant->canUseTransport());
    }

    public function test_stable_on_paid_plan_can_use_transport(): void
    {
        $tenant = $this->makeTenant(TenantType::Stable, 'pro');
        $this->assertTrue($tenant->canUseTransport());

        $stable = $this->makeTenant(TenantType::Stable, 'stable');
        $this->assertTrue($stable->canUseTransport());
    }

    public function test_stable_without_plan_cannot_use_transport(): void
    {
        $tenant = $this->makeTenant(TenantType::Stable, null);
        $this->assertFalse($tenant->canUseTransport());
    }

    public function test_transporter_with_plan_can_use_transport(): void
    {
        $tenant = $this->makeTenant(TenantType::Transporter, 'transport_start');
        $this->assertTrue($tenant->canUseTransport());
    }

    public function test_transporter_without_plan_cannot_use_transport(): void
    {
        $tenant = $this->makeTenant(TenantType::Transporter, null);
        $this->assertFalse($tenant->canUseTransport());
    }

    public function test_suspended_tenant_cannot_use_transport(): void
    {
        $tenant = $this->makeTenant(TenantType::Stable, 'pro', 'suspended');
        $this->assertFalse($tenant->canUseTransport(),
            'isUsable() == false → no access regardless of plan');
    }
}
