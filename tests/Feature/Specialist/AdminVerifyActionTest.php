<?php

declare(strict_types=1);

namespace Tests\Feature\Specialist;

use App\Filament\Admin\Resources\ExternalSpecialistResource;
use App\Models\Central\ExternalSpecialist;
use App\Models\Central\User;
use App\Services\MasterAuditLogger;
use Filament\Tables\Actions\Action;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Verify/unverify actions w ExternalSpecialistResource (PR O5 Channel B).
 * Test izolowany — invoke action callback bezpośrednio przez reflection,
 * bez uruchamiania Filament page mount (które wymaga browser).
 */
class AdminVerifyActionTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(MasterAuditLogger::class, function (MockInterface $m) {
            $m->shouldReceive('record')->andReturnNull();
        });

        $this->adminUser = User::create([
            'name' => 'Master Admin',
            'email' => 'admin-'.uniqid().'@example.com',
            'password' => Hash::make('secret'),
        ]);
        Auth::login($this->adminUser);
    }

    public function test_verify_action_visible_only_when_not_verified(): void
    {
        $action = $this->getAction('verify');

        $unverified = $this->makeSpecialist();
        $action->record($unverified);
        $this->assertTrue($action->isVisible());

        $verified = $this->makeSpecialist();
        $verified->forceFill(['verified_at' => now(), 'verified_by_user_id' => $this->adminUser->id])->save();
        $action->record($verified);
        $this->assertFalse($action->isVisible());
    }

    public function test_verify_action_sets_verified_at_and_verified_by(): void
    {
        $specialist = $this->makeSpecialist();
        $this->assertNull($specialist->verified_at);
        $this->assertFalse($specialist->is_verified);

        $action = $this->getAction('verify');
        $action->record($specialist);

        $callback = $this->getActionCallback($action);
        $callback($specialist);

        $specialist->refresh();
        $this->assertNotNull($specialist->verified_at);
        $this->assertSame($this->adminUser->id, $specialist->verified_by_user_id);
        $this->assertTrue($specialist->is_verified);
    }

    public function test_unverify_action_visible_only_when_verified(): void
    {
        $action = $this->getAction('unverify');

        $unverified = $this->makeSpecialist();
        $action->record($unverified);
        $this->assertFalse($action->isVisible());

        $verified = $this->makeSpecialist();
        $verified->forceFill(['verified_at' => now(), 'verified_by_user_id' => $this->adminUser->id])->save();
        $action->record($verified);
        $this->assertTrue($action->isVisible());
    }

    public function test_unverify_action_clears_verified_at_and_verified_by(): void
    {
        $specialist = $this->makeSpecialist();
        $specialist->forceFill(['verified_at' => now()->subDay(), 'verified_by_user_id' => $this->adminUser->id])->save();
        $this->assertTrue($specialist->refresh()->is_verified);

        $action = $this->getAction('unverify');
        $action->record($specialist);

        $callback = $this->getActionCallback($action);
        $callback($specialist, ['reason' => 'License expired 2026-05']);

        $specialist->refresh();
        $this->assertNull($specialist->verified_at);
        $this->assertNull($specialist->verified_by_user_id);
        $this->assertFalse($specialist->is_verified);
    }

    public function test_unverify_action_records_audit_log_with_reason(): void
    {
        $specialist = $this->makeSpecialist();
        $specialist->forceFill(['verified_at' => now()->subDay(), 'verified_by_user_id' => $this->adminUser->id])->save();

        // Override mock to capture
        $captured = null;
        $this->mock(MasterAuditLogger::class, function (MockInterface $m) use (&$captured) {
            $m->shouldReceive('record')->andReturnUsing(function (string $event, ?string $subject, ?string $id, ?string $tenantId, array $payload) use (&$captured) {
                $captured = ['event' => $event, 'payload' => $payload];
            });
        });

        $action = $this->getAction('unverify');
        $action->record($specialist);
        $callback = $this->getActionCallback($action);
        $callback($specialist->refresh(), ['reason' => 'Suspicious activity reported by stable']);

        $this->assertSame('external_specialist.unverified', $captured['event']);
        $this->assertSame('Suspicious activity reported by stable', $captured['payload']['reason']);
        $this->assertNotNull($captured['payload']['previous_verified_at']);
    }

    private function makeSpecialist(): ExternalSpecialist
    {
        return ExternalSpecialist::create([
            'email' => 'vet-'.uniqid().'@example.com',
            'display_name' => 'dr Test',
            'specialty' => 'vet',
        ]);
    }

    /**
     * Reflection-fetch prywatnej akcji (verify / unverify) z resource'a.
     * Filament actions są zwracane przez prywatne method'y — testujemy
     * bez uruchamiania pełnego Filament page'a.
     */
    private function getAction(string $name): Action
    {
        $method = new ReflectionMethod(
            ExternalSpecialistResource::class,
            $name.'Action',
        );
        $method->setAccessible(true);

        /** @var Action $action */
        $action = $method->invoke(null);

        return $action;
    }

    /**
     * Wyciągnij callback z Filament action — używamy property reflection
     * bo Filament nie wystawia publicznej metody invoke'owej.
     */
    private function getActionCallback(Action $action): \Closure
    {
        // Filament Action stores action callback w protected property `$action`.
        // Łatwiej wywołać przez Filament's `call()` ale to wymaga component
        // context. Sięgnij po raw closure.
        $reflection = new \ReflectionClass($action);
        $prop = $reflection->getProperty('action');
        $prop->setAccessible(true);
        $value = $prop->getValue($action);

        return $value instanceof \Closure ? $value : (function () {});
    }
}
