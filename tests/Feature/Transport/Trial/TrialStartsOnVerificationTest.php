<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Trial;

use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use Database\Seeders\TransportPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Marketing spec: trial 1mc startuje OD WERYFIKACJI, nie od signupu.
 * Test krytyczny — błąd tu daje darmowy miesiąc niezweryfikowanym (i pozwala
 * nieświadomie billować klientów którzy nigdy nie przeszli weryfikacji).
 */
class TrialStartsOnVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeTransporter(VerificationStatus $vstatus, ?Carbon $trialEnds = null, ?string $planCode = 'transport_start'): Tenant
    {
        TransportPlansSeeder::seed();
        $plan = Plan::where('code', $planCode)->first();

        $tenant = new Tenant([
            'slug' => 'tt-'.uniqid(),
            'name' => 'T',
            'type' => TenantType::Transporter,
            'verification_status' => $vstatus,
            'db_name' => 'test_'.uniqid(),
            'db_username' => 'test_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'provisioning',
            'plan_id' => $plan?->id,
            'trial_ends_at' => $trialEnds,
        ]);
        $tenant->save();

        return $tenant->fresh();
    }

    public function test_unverified_transporter_get_no_trial(): void
    {
        $tenant = $this->makeTransporter(VerificationStatus::Pending);
        $this->assertNull($tenant->trial_ends_at);

        $tenant->startTrialOnVerification();
        $tenant->refresh();
        $this->assertNull($tenant->trial_ends_at,
            'Pending → no trial start (must wait for verification flip first)');
    }

    public function test_verified_transporter_gets_30_day_trial(): void
    {
        Carbon::setTestNow('2026-06-01 10:00:00');

        $tenant = $this->makeTransporter(VerificationStatus::Verified);
        $tenant->startTrialOnVerification();
        $tenant->refresh();

        $this->assertNotNull($tenant->trial_ends_at);
        $this->assertSame('2026-07-01 10:00:00', $tenant->trial_ends_at->format('Y-m-d H:i:s'));

        // Status flipped to trialing so tenant can log in immediately.
        $this->assertSame('trialing', $tenant->status);

        Carbon::setTestNow();
    }

    public function test_method_is_idempotent_no_reset(): void
    {
        Carbon::setTestNow('2026-06-01');

        $tenant = $this->makeTransporter(VerificationStatus::Verified);
        $tenant->startTrialOnVerification();
        $firstEndsAt = $tenant->fresh()->trial_ends_at;

        // Adwance time and call again — should NOT reset
        Carbon::setTestNow('2026-06-15');
        $tenant->refresh()->startTrialOnVerification();
        $tenant->refresh();

        $this->assertEquals(
            $firstEndsAt->toDateTimeString(),
            $tenant->trial_ends_at->toDateTimeString(),
            'Re-calling startTrialOnVerification must NOT reset trial_ends_at',
        );

        Carbon::setTestNow();
    }

    public function test_stable_tenant_unaffected(): void
    {
        $stable = new Tenant([
            'slug' => 'st-'.uniqid(),
            'name' => 'S',
            'type' => TenantType::Stable,
            'db_name' => 'test_'.uniqid(),
            'db_username' => 'test_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'trialing',
            'trial_ends_at' => null,
        ]);
        $stable->save();

        $stable->refresh()->startTrialOnVerification();
        $this->assertNull($stable->fresh()->trial_ends_at,
            'Method is no-op for non-transporters');
    }
}
