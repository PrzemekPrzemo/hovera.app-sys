<?php

declare(strict_types=1);

namespace Tests\Feature\Specialist;

use App\Models\Central\ExternalSpecialist;
use App\Models\Central\SpecialistMagicLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneMagicLinksCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_removes_expired_and_old_used_links(): void
    {
        $specialist = ExternalSpecialist::create([
            'email' => 'prune-'.uniqid().'@example.com',
            'display_name' => 'dr Prune',
        ]);

        // Recent (keep)
        SpecialistMagicLink::issue($specialist, SpecialistMagicLink::KIND_INITIAL_SETUP);

        // Old expired (prune)
        $oldExpired = SpecialistMagicLink::issue($specialist, SpecialistMagicLink::KIND_LOGIN)['model'];
        $oldExpired->forceFill(['expires_at' => now()->subDays(10)])->save();

        // Old used (prune)
        $oldUsed = SpecialistMagicLink::issue($specialist, SpecialistMagicLink::KIND_PASSWORD_RESET)['model'];
        $oldUsed->forceFill(['used_at' => now()->subDays(60)])->save();

        $this->artisan('specialists:prune-magic-links')
            ->expectsOutputToContain('Pruned 2 magic links.')
            ->assertExitCode(0);

        $this->assertSame(1, SpecialistMagicLink::count());
    }

    public function test_command_returns_zero_when_nothing_to_prune(): void
    {
        $specialist = ExternalSpecialist::create([
            'email' => 'fresh-'.uniqid().'@example.com',
            'display_name' => 'dr Fresh',
        ]);
        SpecialistMagicLink::issue($specialist, SpecialistMagicLink::KIND_INITIAL_SETUP);

        $this->artisan('specialists:prune-magic-links')
            ->expectsOutputToContain('Pruned 0 magic links.')
            ->assertExitCode(0);

        $this->assertSame(1, SpecialistMagicLink::count());
    }
}
