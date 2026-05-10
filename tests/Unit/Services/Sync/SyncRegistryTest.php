<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Sync;

use App\Models\Central\TenantMembership;
use App\Services\Sync\SyncRegistry;
use PHPUnit\Framework\TestCase;

class SyncRegistryTest extends TestCase
{
    private SyncRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        // Inline config so we don't need Laravel container.
        $config = [
            'horses' => [
                'mutate_roles' => ['manager', 'groom'],
                'conflict' => SyncRegistry::CONFLICT_LWW,
            ],
            'invoices' => [
                'mutate_roles' => null, // server-only
                'conflict' => SyncRegistry::CONFLICT_SERVER_ONLY,
            ],
            'horse_photos' => [
                'mutate_roles' => 'any',
                'conflict' => SyncRegistry::CONFLICT_APPEND_ONLY,
            ],
        ];
        $GLOBALS['__sync_entities'] = $config;

        $this->registry = new class extends SyncRegistry
        {
            public function entities(): array
            {
                return $GLOBALS['__sync_entities'];
            }
        };
    }

    public function test_can_mutate_returns_true_when_role_listed(): void
    {
        $m = new TenantMembership(['role' => 'manager']);
        $this->assertTrue($this->registry->canMutate('horses', $m));
    }

    public function test_can_mutate_returns_false_for_unlisted_role(): void
    {
        $m = new TenantMembership(['role' => 'client']);
        $this->assertFalse($this->registry->canMutate('horses', $m));
    }

    public function test_server_only_entity_rejects_every_role(): void
    {
        $m = new TenantMembership(['role' => 'manager']);
        $this->assertFalse($this->registry->canMutate('invoices', $m));
    }

    public function test_any_role_marker_lets_anyone_mutate(): void
    {
        $m = new TenantMembership(['role' => 'client']);
        $this->assertTrue($this->registry->canMutate('horse_photos', $m));
    }

    public function test_conflict_policy_lookup(): void
    {
        $this->assertSame(SyncRegistry::CONFLICT_LWW, $this->registry->conflictPolicy('horses'));
        $this->assertSame(SyncRegistry::CONFLICT_APPEND_ONLY, $this->registry->conflictPolicy('horse_photos'));
        $this->assertSame(SyncRegistry::CONFLICT_LWW, $this->registry->conflictPolicy('does_not_exist'));
    }
}
