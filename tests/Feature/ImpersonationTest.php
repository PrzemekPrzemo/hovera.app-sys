<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Impersonation\StartImpersonation;
use App\Actions\Impersonation\StopImpersonation;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_admin_can_impersonate_a_tenant_user(): void
    {
        [$master, $tenant, $target, $membership] = $this->scenario();

        Auth::login($master);
        $session = $this->app['session']->driver();
        $session->setRequestOnHandler(Request::create('/'));

        $result = $this->app->make(StartImpersonation::class)->execute(
            masterAdmin: $master,
            tenant: $tenant,
            targetUser: $target,
            reason: 'Diagnozuję problem z karnetem #42 zgłoszony przez klienta.',
            session: $session,
        );

        $this->assertSame($target->id, Auth::id());
        $this->assertSame($master->id, $session->get('impersonation.original_user_id'));
        $this->assertSame($result['session_id'], $session->get('impersonation.session_id'));
        $this->assertSame($tenant->id, $session->get('current_tenant_id'));
        $this->assertSame($result['session_id'], $session->get('impersonation_session_id'));

        // Persisted record
        $row = DB::connection('central')->table('impersonation_sessions')->where('id', $result['session_id'])->first();
        $this->assertNotNull($row);
        $this->assertSame($master->id, $row->master_user_id);
        $this->assertSame($target->id, $row->target_user_id);
        $this->assertSame($tenant->id, $row->tenant_id);
        $this->assertNull($row->ended_at);
    }

    public function test_non_master_admin_is_rejected(): void
    {
        [$master, $tenant, $target, $membership] = $this->scenario();

        $regular = User::create([
            'email' => 'reg@example.com',
            'name' => 'Regular',
            'password' => Hash::make('secret'),
            'is_master_admin' => false,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->app->make(StartImpersonation::class)->execute(
            masterAdmin: $regular,
            tenant: $tenant,
            targetUser: $target,
            reason: 'I should not be allowed.',
            session: $this->app['session']->driver(),
        );
    }

    public function test_short_reason_is_rejected(): void
    {
        [$master, $tenant, $target] = $this->scenario();

        $this->expectException(\RuntimeException::class);
        $this->app->make(StartImpersonation::class)->execute(
            masterAdmin: $master,
            tenant: $tenant,
            targetUser: $target,
            reason: 'no',
            session: $this->app['session']->driver(),
        );
    }

    public function test_target_without_active_membership_is_rejected(): void
    {
        [$master, $tenant, $target, $membership] = $this->scenario();
        $membership->forceFill(['revoked_at' => now()])->save();

        $this->expectException(\RuntimeException::class);
        $this->app->make(StartImpersonation::class)->execute(
            masterAdmin: $master,
            tenant: $tenant,
            targetUser: $target,
            reason: 'Target was kicked out, no impersonation possible.',
            session: $this->app['session']->driver(),
        );
    }

    public function test_stop_returns_to_original_user_and_marks_session_ended(): void
    {
        [$master, $tenant, $target] = $this->scenario();

        Auth::login($master);
        $session = $this->app['session']->driver();

        $start = $this->app->make(StartImpersonation::class)->execute(
            masterAdmin: $master,
            tenant: $tenant,
            targetUser: $target,
            reason: 'Powód testowy z conajmniej dziesięcioma znakami.',
            session: $session,
        );

        $this->assertSame($target->id, Auth::id());

        $result = $this->app->make(StopImpersonation::class)->execute($session);

        $this->assertSame($master->id, Auth::id());
        $this->assertSame($master->id, $result['returned_to']->id);
        $this->assertNull($session->get('impersonation.session_id'));
        $this->assertNull($session->get('current_tenant_id'));
        $this->assertNull($session->get('impersonation_session_id'));

        $row = DB::connection('central')->table('impersonation_sessions')->where('id', $start['session_id'])->first();
        $this->assertNotNull($row->ended_at);
    }

    public function test_stop_without_active_session_is_safe(): void
    {
        [$master] = $this->scenario();
        Auth::login($master);

        $session = $this->app['session']->driver();
        $result = $this->app->make(StopImpersonation::class)->execute($session);

        $this->assertNull($result['returned_to']);
    }

    public function test_stop_endpoint_returns_to_admin_panel(): void
    {
        [$master, $tenant, $target] = $this->scenario();

        $this->actingAs($master);
        $session = $this->app['session']->driver();

        $this->app->make(StartImpersonation::class)->execute(
            masterAdmin: $master,
            tenant: $tenant,
            targetUser: $target,
            reason: 'Powód testowy z conajmniej dziesięcioma znakami.',
            session: $session,
        );

        $response = $this->post('/impersonation/stop');

        $response->assertRedirect('/'.config('hovera.admin.path'));
    }

    public function test_start_route_consumes_session_intent_and_logs_in_target(): void
    {
        [$master, $tenant, $target] = $this->scenario();

        $response = $this->actingAs($master)
            ->withSession([
                'impersonation.intent' => [
                    'tenant_id' => $tenant->id,
                    'target_user_id' => $target->id,
                    'reason' => 'Diagnostyka problemu z konfiguracją stajni.',
                    'issued_at' => now()->timestamp,
                ],
            ])
            ->get('/impersonation/start');

        $response->assertRedirect('/app');
        $this->assertSame($target->id, Auth::id());
        $this->assertNull(session('impersonation.intent'), 'intent should be pulled');
        $this->assertSame($master->id, session('impersonation.original_user_id'));
    }

    public function test_start_route_rejects_expired_intent(): void
    {
        [$master, $tenant, $target] = $this->scenario();

        $this->actingAs($master)
            ->withSession([
                'impersonation.intent' => [
                    'tenant_id' => $tenant->id,
                    'target_user_id' => $target->id,
                    'reason' => 'Coś tam coś tam co najmniej pięć znaków.',
                    'issued_at' => now()->subMinutes(5)->timestamp,
                ],
            ])
            ->get('/impersonation/start')
            ->assertStatus(400);

        $this->assertSame($master->id, Auth::id());
    }

    public function test_start_route_aborts_when_no_intent_in_session(): void
    {
        [$master] = $this->scenario();

        $this->actingAs($master)
            ->get('/impersonation/start')
            ->assertStatus(400);
    }

    /**
     * @return array{0:User,1:Tenant,2:User,3:TenantMembership}
     */
    private function scenario(): array
    {
        $master = User::create([
            'email' => 'master@example.com',
            'name' => 'Master Admin',
            'password' => Hash::make('secret'),
            'is_master_admin' => true,
        ]);

        $tenant = new Tenant([
            'slug' => 'acme',
            'name' => 'Acme',
            'db_name' => 'hovera_t_acme',
            'db_username' => 'hovera_t_acme',
            'status' => 'active',
        ]);
        $tenant->db_password = 'irrelevant';
        $tenant->save();

        $target = User::create([
            'email' => 'owner@example.com',
            'name' => 'Owner',
            'password' => Hash::make('secret'),
        ]);

        $membership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $target->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        return [$master, $tenant, $target, $membership];
    }
}
