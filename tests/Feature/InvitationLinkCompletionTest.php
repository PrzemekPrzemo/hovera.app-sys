<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Tenants\CreateTenant;
use App\Enums\TenantType;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Pokrywa PR 4 z TODO — invitation link completion. Owner rejestruje się
 * przez /register/horse-owner?stable={ulid} → walidujemy że stable istnieje
 * + jest active + type=stable. Po sukcesie zapisujemy `invite_origin` w
 * Tenant.settings i pokazujemy ackn na thanks page.
 *
 * Full auto-create HorseBoardingAssignment.pending wymaga dodania konia
 * przez owner'a — to osobny scope (planowany jako follow-up PR po PR 3).
 */
class InvitationLinkCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Plan::firstOrCreate(['code' => 'owner_free'], [
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

        // Mockujemy CreateTenant żeby nie odpalić Provisioner'a (MySQL).
        $this->mock(CreateTenant::class, function (MockInterface $m) {
            $m->shouldReceive('execute')->andReturnUsing(function (array $input) {
                $tenant = new Tenant([
                    'slug' => $input['slug'],
                    'name' => $input['name'],
                    'type' => TenantType::HorseOwner,
                    'country' => 'PL',
                    'locale' => 'pl',
                    'timezone' => 'Europe/Warsaw',
                    'currency' => 'PLN',
                    'db_name' => 'hovera_t_'.Str::random(8),
                    'db_username' => 'hovera_t_'.Str::random(8),
                    'db_password_encrypted' => Crypt::encryptString('x'),
                    'status' => 'active',
                ]);
                $tenant->save();

                return $tenant;
            });
        });

        Notification::fake();
    }

    public function test_show_renders_form_with_invite_query_params(): void
    {
        $stable = $this->makeStable();

        $response = $this->get(route('register.horse-owner.show', [
            'stable' => $stable->id,
            'token' => 'doesnt-matter-yet',
        ]));

        $response->assertOk();
        $response->assertViewHas('invite_stable_id', $stable->id);
        $response->assertViewHas('invite_token', 'doesnt-matter-yet');
    }

    public function test_submit_persists_invite_origin_when_stable_valid(): void
    {
        $stable = $this->makeStable('Stajnia Iskra');

        $response = $this->post(route('register.horse-owner.submit'), [
            'owner_name' => 'Jan Owner',
            'owner_email' => 'jan-'.uniqid().'@example.test',
            'terms' => '1',
            'invite_stable_id' => $stable->id,
        ]);

        $response->assertRedirect();

        $newTenant = Tenant::query()
            ->where('type', TenantType::HorseOwner)
            ->where('slug', 'like', 'jan-%')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($newTenant);
        $settings = (array) $newTenant->settings;
        $this->assertArrayHasKey('invite_origin', $settings);
        $this->assertSame($stable->id, $settings['invite_origin']['stable_tenant_id']);
        $this->assertSame('Stajnia Iskra', $settings['invite_origin']['stable_name']);
    }

    public function test_submit_ignores_invalid_invite_stable_id(): void
    {
        $response = $this->post(route('register.horse-owner.submit'), [
            'owner_name' => 'Jan',
            'owner_email' => 'jan-'.uniqid().'@example.test',
            'terms' => '1',
            'invite_stable_id' => 'not-a-real-ulid',
        ]);

        $response->assertRedirect();

        $newTenant = Tenant::query()->where('type', TenantType::HorseOwner)->latest('created_at')->first();
        $this->assertNotNull($newTenant);

        $settings = (array) $newTenant->settings;
        $this->assertArrayNotHasKey('invite_origin', $settings, 'Invalid invite ID nie powinien tworzyć invite_origin (anti-injection)');
    }

    public function test_submit_ignores_invite_to_transporter_tenant(): void
    {
        // User wkleił link z innego tenant'a (np. firmy transportowej).
        // Nie powinien się przykleić do horse owner registration.
        $transporter = Tenant::create([
            'slug' => 'tr-'.uniqid(),
            'name' => 'Transport Co',
            'type' => TenantType::Transporter,
            'country' => 'PL',
            'locale' => 'pl',
            'timezone' => 'Europe/Warsaw',
            'currency' => 'PLN',
            'db_name' => 'hovera_t_tr_'.Str::random(8),
            'db_username' => 'hovera_t_tr_'.Str::random(8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);

        $this->post(route('register.horse-owner.submit'), [
            'owner_name' => 'Jan',
            'owner_email' => 'jan-tr-'.uniqid().'@example.test',
            'terms' => '1',
            'invite_stable_id' => $transporter->id,
        ]);

        $newTenant = Tenant::query()->where('type', TenantType::HorseOwner)->latest('created_at')->first();
        $settings = (array) $newTenant->settings;
        $this->assertArrayNotHasKey('invite_origin', $settings);
    }

    public function test_thanks_page_renders_invite_origin_banner(): void
    {
        $stable = $this->makeStable('Stajnia Pegaz');

        $owner = Tenant::create([
            'slug' => 'jan-thanks-'.uniqid(),
            'name' => 'Jan Owner',
            'type' => TenantType::HorseOwner,
            'country' => 'PL',
            'locale' => 'pl',
            'timezone' => 'Europe/Warsaw',
            'currency' => 'PLN',
            'db_name' => 'hovera_t_'.Str::random(8),
            'db_username' => 'hovera_t_'.Str::random(8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
            'settings' => [
                'invite_origin' => [
                    'stable_tenant_id' => $stable->id,
                    'stable_name' => 'Stajnia Pegaz',
                ],
            ],
        ]);

        $response = $this->get(route('register.horse-owner.thanks', ['slug' => $owner->slug]));

        $response->assertOk();
        $response->assertSee('Stajnia Pegaz', escape: false);
    }

    private function makeStable(string $name = 'Test Stable'): Tenant
    {
        return Tenant::create([
            'slug' => 's-'.uniqid(),
            'name' => $name,
            'type' => TenantType::Stable,
            'country' => 'PL',
            'locale' => 'pl',
            'timezone' => 'Europe/Warsaw',
            'currency' => 'PLN',
            'db_name' => 'hovera_t_s_'.Str::random(8),
            'db_username' => 'hovera_t_s_'.Str::random(8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }
}
