<?php

declare(strict_types=1);

namespace Tests\Feature\Specialist;

use App\Filament\App\Resources\SpecialistResource;
use App\Models\Central\ExternalSpecialist;
use App\Models\Tenant\Specialist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

/**
 * PR O5 Channel B (epic 1.2) — autolink lokalnego kontaktu Specialist
 * (tenant DB) z zarejestrowanym ExternalSpecialist (central DB) po e-mailu.
 */
class SpecialistAutolinkTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_spec_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        Schema::connection('tenant')->create('specialists', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('type', 16);
            $t->string('central_user_id', 26)->nullable();
            $t->string('external_specialist_id', 26)->nullable();
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('color', 7)->nullable();
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_resolve_returns_id_for_matching_email(): void
    {
        $specialist = ExternalSpecialist::create([
            'email' => 'match@example.com',
            'display_name' => 'dr Match',
            'specialty' => 'vet',
        ]);

        $resolved = $this->resolve('match@example.com');

        $this->assertSame($specialist->id, $resolved);
    }

    public function test_resolve_returns_null_for_unknown_email(): void
    {
        $this->assertNull($this->resolve('nobody@example.com'));
    }

    public function test_resolve_returns_null_for_blank_email(): void
    {
        $this->assertNull($this->resolve(null));
        $this->assertNull($this->resolve(''));
    }

    public function test_local_specialist_relation_resolves_external_identity(): void
    {
        $external = ExternalSpecialist::create([
            'email' => 'linked@example.com',
            'display_name' => 'dr Linked',
            'specialty' => 'vet',
        ]);

        $local = Specialist::create([
            'id' => '01HSPEC0000000000000000001',
            'type' => Specialist::TYPE_VET,
            'name' => 'dr Linked',
            'email' => 'linked@example.com',
            'external_specialist_id' => $external->id,
        ]);

        $this->assertTrue($local->isLinkedToExternalSpecialist());
        $this->assertSame($external->id, $local->externalSpecialist->id);
    }

    public function test_badge_reflects_verified_state(): void
    {
        ExternalSpecialist::create([
            'email' => 'verified@example.com',
            'display_name' => 'dr Verified',
            'specialty' => 'vet',
            'password_hash' => Hash::make('haslo-12345'),
            'email_verified_at' => now(),
            'verified_at' => now(),
        ]);

        ExternalSpecialist::create([
            'email' => 'unverified@example.com',
            'display_name' => 'dr Unverified',
            'specialty' => 'vet',
        ]);

        $verifiedBadge = $this->badge('verified@example.com');
        $unverifiedBadge = $this->badge('unverified@example.com');
        $noneBadge = $this->badge('stranger@example.com');

        $this->assertStringContainsString(__('app/specialist.form.external_link.verified'), $verifiedBadge);
        $this->assertStringContainsString(__('app/specialist.form.external_link.unverified'), $unverifiedBadge);
        $this->assertStringContainsString(__('app/specialist.form.external_link.none'), $noneBadge);
    }

    private function resolve(?string $email): ?string
    {
        $method = new ReflectionMethod(SpecialistResource::class, 'resolveExternalSpecialistId');
        $method->setAccessible(true);

        return $method->invoke(null, $email);
    }

    private function badge(?string $email): string
    {
        $method = new ReflectionMethod(SpecialistResource::class, 'externalLinkBadge');
        $method->setAccessible(true);

        return (string) $method->invoke(null, $email);
    }
}
