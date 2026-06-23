<?php

declare(strict_types=1);

namespace Tests\Feature\Specialist;

use App\Models\Central\ExternalSpecialist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * PR O5 Channel B — SpecialistPanelProvider (Filament panel + guard `specialist`).
 *
 * Bramki dostępu:
 *   - gość → redirect na /specialist/login
 *   - specjalista po dokończonym setup'ie → 200
 *   - specjalista bez setup'u → 403 (canAccessPanel false)
 */
class SpecialistPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_specialist_login(): void
    {
        $response = $this->get('/specialist');

        $response->assertRedirect(route('filament.specialist.auth.login'));
    }

    public function test_specialist_login_page_is_reachable(): void
    {
        $this->get('/specialist/login')->assertOk();
    }

    public function test_specialist_with_completed_setup_can_access_panel(): void
    {
        $specialist = $this->makeSpecialist(completedSetup: true);

        $response = $this->actingAs($specialist, 'specialist')->get('/specialist');

        $response->assertOk();
    }

    public function test_specialist_without_completed_setup_is_forbidden(): void
    {
        // Hasło ustawione, ale email NIE zweryfikowany → has_completed_setup false.
        $specialist = ExternalSpecialist::create([
            'email' => 'incomplete@example.com',
            'display_name' => 'dr Incomplete',
            'specialty' => 'vet',
            'password_hash' => Hash::make('haslo-12345'),
        ]);

        $this->assertFalse($specialist->has_completed_setup);

        $response = $this->actingAs($specialist, 'specialist')->get('/specialist');

        $response->assertForbidden();
    }

    public function test_master_admin_verification_is_not_required_to_access_panel(): void
    {
        // verified_at null (niezweryfikowany przez Hovera-admin), ale setup done.
        $specialist = $this->makeSpecialist(completedSetup: true);

        $this->assertFalse($specialist->is_verified);

        $this->actingAs($specialist, 'specialist')->get('/specialist')->assertOk();
    }

    private function makeSpecialist(bool $completedSetup = false): ExternalSpecialist
    {
        return ExternalSpecialist::create([
            'email' => 'vet-'.uniqid().'@example.com',
            'display_name' => 'dr Test',
            'specialty' => 'vet',
            'password_hash' => $completedSetup ? Hash::make('haslo-12345') : null,
            'email_verified_at' => $completedSetup ? now() : null,
        ]);
    }
}
