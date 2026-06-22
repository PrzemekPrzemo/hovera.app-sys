<?php

declare(strict_types=1);

namespace Tests\Feature\Specialist;

use App\Models\Central\ExternalSpecialist;
use App\Models\Central\SpecialistMagicLink;
use App\Models\Central\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * PR O5 Channel B foundation — ExternalSpecialist + SpecialistMagicLink
 * models, magic link issuance / redemption flow, account setup state.
 *
 * Per captured decisions §3 (hybrid invite):
 *   - 7d magic link initial_setup
 *   - unverified badge dopóki master-admin nie potwierdzi PWZ
 */
class ExternalSpecialistTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_specialist_created_unverified_and_setup_incomplete(): void
    {
        $specialist = ExternalSpecialist::create([
            'email' => 'vet@example.com',
            'display_name' => 'dr Anna Vetinari',
            'specialty' => 'vet',
        ]);

        $this->assertFalse($specialist->is_verified);
        $this->assertFalse($specialist->has_completed_setup);
        $this->assertNull($specialist->verified_at);
        $this->assertNull($specialist->password_hash);
        $this->assertNull($specialist->email_verified_at);
    }

    public function test_specialist_completed_setup_when_password_and_email_verified(): void
    {
        $specialist = ExternalSpecialist::create([
            'email' => 'vet2@example.com',
            'display_name' => 'dr Anna',
            'password_hash' => Hash::make('secret'),
            'email_verified_at' => now(),
        ]);

        $this->assertTrue($specialist->has_completed_setup);
        $this->assertFalse($specialist->is_verified); // jeszcze nie przez Hovera-admin
    }

    public function test_specialist_verified_when_admin_confirms(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-'.uniqid().'@example.com',
            'password' => Hash::make('secret'),
        ]);

        $specialist = ExternalSpecialist::create([
            'email' => 'vet-v@example.com',
            'display_name' => 'dr Verified',
            'verified_at' => now(),
            'verified_by_user_id' => $admin->id,
        ]);

        $this->assertTrue($specialist->is_verified);
        $this->assertSame($admin->id, $specialist->verifiedBy->id);
    }

    public function test_email_unique_constraint(): void
    {
        ExternalSpecialist::create([
            'email' => 'dup@example.com',
            'display_name' => 'First',
        ]);

        $this->expectException(QueryException::class);
        ExternalSpecialist::create([
            'email' => 'dup@example.com',
            'display_name' => 'Second',
        ]);
    }

    public function test_magic_link_issue_returns_plain_token_and_persists_hash_only(): void
    {
        $specialist = $this->makeSpecialist();

        $result = SpecialistMagicLink::issue($specialist, SpecialistMagicLink::KIND_INITIAL_SETUP);

        $this->assertSame(64, strlen($result['plain_token'])); // 256-bit hex
        $link = $result['model'];

        // Hash != plain token
        $this->assertNotSame($result['plain_token'], $link->token_hash);
        $this->assertSame(hash('sha256', $result['plain_token']), $link->token_hash);
        $this->assertSame(SpecialistMagicLink::KIND_INITIAL_SETUP, $link->kind);
        $this->assertNull($link->used_at);
        // 7d TTL ± a bit (rounding)
        $this->assertGreaterThan(now()->addDays(6), $link->expires_at);
        $this->assertLessThan(now()->addDays(8), $link->expires_at);
    }

    public function test_magic_link_kinds_have_different_ttls(): void
    {
        $specialist = $this->makeSpecialist();

        $setup = SpecialistMagicLink::issue($specialist, SpecialistMagicLink::KIND_INITIAL_SETUP)['model'];
        $reset = SpecialistMagicLink::issue($specialist, SpecialistMagicLink::KIND_PASSWORD_RESET)['model'];
        $login = SpecialistMagicLink::issue($specialist, SpecialistMagicLink::KIND_LOGIN)['model'];

        // setup ~7d, reset ~1h, login ~15min — verify ordering
        $this->assertGreaterThan($reset->expires_at, $setup->expires_at);
        $this->assertGreaterThan($login->expires_at, $reset->expires_at);
    }

    public function test_magic_link_invalid_kind_throws(): void
    {
        $specialist = $this->makeSpecialist();

        $this->expectException(\InvalidArgumentException::class);
        SpecialistMagicLink::issue($specialist, 'nonexistent_kind');
    }

    public function test_find_by_plain_token_succeeds_for_valid_link(): void
    {
        $specialist = $this->makeSpecialist();
        $result = SpecialistMagicLink::issue($specialist, SpecialistMagicLink::KIND_INITIAL_SETUP);

        $found = SpecialistMagicLink::findByPlainToken($result['plain_token'], SpecialistMagicLink::KIND_INITIAL_SETUP);

        $this->assertNotNull($found);
        $this->assertSame($result['model']->id, $found->id);
    }

    public function test_find_by_plain_token_fails_for_wrong_kind(): void
    {
        $specialist = $this->makeSpecialist();
        $result = SpecialistMagicLink::issue($specialist, SpecialistMagicLink::KIND_INITIAL_SETUP);

        $found = SpecialistMagicLink::findByPlainToken($result['plain_token'], SpecialistMagicLink::KIND_LOGIN);

        $this->assertNull($found);
    }

    public function test_find_by_plain_token_fails_for_expired_link(): void
    {
        $specialist = $this->makeSpecialist();
        $result = SpecialistMagicLink::issue($specialist, SpecialistMagicLink::KIND_INITIAL_SETUP);
        // Manually expire
        $result['model']->forceFill(['expires_at' => now()->subHour()])->save();

        $found = SpecialistMagicLink::findByPlainToken($result['plain_token'], SpecialistMagicLink::KIND_INITIAL_SETUP);

        $this->assertNull($found);
    }

    public function test_find_by_plain_token_fails_for_used_link(): void
    {
        $specialist = $this->makeSpecialist();
        $result = SpecialistMagicLink::issue($specialist, SpecialistMagicLink::KIND_INITIAL_SETUP);
        $result['model']->markUsed();

        $found = SpecialistMagicLink::findByPlainToken($result['plain_token'], SpecialistMagicLink::KIND_INITIAL_SETUP);

        $this->assertNull($found);
    }

    public function test_mark_used_is_idempotent(): void
    {
        $specialist = $this->makeSpecialist();
        $result = SpecialistMagicLink::issue($specialist, SpecialistMagicLink::KIND_INITIAL_SETUP);
        $link = $result['model'];

        $link->markUsed();
        $firstUsed = $link->refresh()->used_at;

        $link->markUsed(); // ponowne
        $secondUsed = $link->refresh()->used_at;

        $this->assertEquals($firstUsed, $secondUsed);
    }

    public function test_prune_expired_removes_old_links(): void
    {
        $specialist = $this->makeSpecialist();

        // Recent — keep
        SpecialistMagicLink::issue($specialist, SpecialistMagicLink::KIND_INITIAL_SETUP);

        // Old expired — prune (expires_at < now-7d)
        $oldExpired = SpecialistMagicLink::issue($specialist, SpecialistMagicLink::KIND_LOGIN)['model'];
        $oldExpired->forceFill(['expires_at' => now()->subDays(10)])->save();

        // Used long ago — prune (used_at < now-30d)
        $oldUsed = SpecialistMagicLink::issue($specialist, SpecialistMagicLink::KIND_PASSWORD_RESET)['model'];
        $oldUsed->forceFill(['used_at' => now()->subDays(60)])->save();

        $pruned = SpecialistMagicLink::pruneExpired();

        $this->assertSame(2, $pruned);
        $this->assertSame(1, SpecialistMagicLink::count());
    }

    public function test_specialist_route_notification_for_mail_returns_email(): void
    {
        $specialist = $this->makeSpecialist('notify@example.com');

        $this->assertSame('notify@example.com', $specialist->routeNotificationForMail());
    }

    private function makeSpecialist(?string $email = null): ExternalSpecialist
    {
        return ExternalSpecialist::create([
            'email' => $email ?? 'vet-'.uniqid().'@example.com',
            'display_name' => 'dr Test',
            'specialty' => 'vet',
        ]);
    }
}
