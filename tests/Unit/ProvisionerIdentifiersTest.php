<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Tenancy\Provisioner;
use App\Tenancy\TenantManager;
use Illuminate\Database\DatabaseManager;
use Tests\TestCase;

/**
 * Pure unit tests for identifier sanitisation. We don't need a real
 * DatabaseManager / TenantManager for these — they're never invoked.
 */
class ProvisionerIdentifiersTest extends TestCase
{
    public function test_simple_slug_produces_prefixed_identifiers(): void
    {
        $p = $this->makeProvisioner();
        $ids = $p->makeIdentifiers('stajnia-wisla');

        $this->assertSame('hovera_t_stajnia_wisla', $ids['db_name']);
        $this->assertSame('hovera_t_stajnia_wisla', $ids['db_user']);
    }

    public function test_special_characters_are_sanitised(): void
    {
        $p = $this->makeProvisioner();
        $ids = $p->makeIdentifiers('Stajnia! Wisła#1');

        // sanitise: lowercase + replace non [a-z0-9_] with _
        $this->assertMatchesRegularExpression('/^hovera_t_[a-z0-9_]+$/', $ids['db_name']);
    }

    public function test_db_user_is_capped_at_mysql_limit(): void
    {
        $p = $this->makeProvisioner();
        $ids = $p->makeIdentifiers(str_repeat('a', 100));

        // MySQL user names are limited to 32 chars
        $this->assertLessThanOrEqual(32, strlen($ids['db_user']));
    }

    public function test_db_name_is_capped_at_63(): void
    {
        $p = $this->makeProvisioner();
        $ids = $p->makeIdentifiers(str_repeat('x', 100));

        $this->assertLessThanOrEqual(63, strlen($ids['db_name']));
    }

    public function test_password_generation_returns_strong_string(): void
    {
        $p = $this->makeProvisioner();

        $pw1 = $p->generatePassword(32);
        $pw2 = $p->generatePassword(32);

        $this->assertSame(32, strlen($pw1));
        $this->assertNotSame($pw1, $pw2);
        // No symbols (we passed `symbols: false` to Str::password)
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]+$/', $pw1);
    }

    private function makeProvisioner(): Provisioner
    {
        // Mocks are fine — these methods don't touch the DB.
        $tm = $this->createStub(TenantManager::class);
        $dm = $this->createStub(DatabaseManager::class);
        return new Provisioner($tm, $dm);
    }
}
