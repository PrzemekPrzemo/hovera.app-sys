<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\Favorites\TransportFavoriteManager;
use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TransportFavorite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class TransportFavoriteManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_limit_is_five(): void
    {
        $this->assertSame(5, app(TransportFavoriteManager::class)->limit());
    }

    public function test_limit_configurable_via_env(): void
    {
        config()->set('transport.favorites.limit', 3);
        $this->assertSame(3, app(TransportFavoriteManager::class)->limit());
    }

    public function test_add_creates_favorite_for_verified_transporter(): void
    {
        $stable = $this->makeStable();
        $transporter = $this->makeTransporter(VerificationStatus::Verified);

        $added = app(TransportFavoriteManager::class)->add($stable, $transporter);

        $this->assertTrue($added);
        $this->assertSame(1, TransportFavorite::query()
            ->where('stable_tenant_id', $stable->id)->count());
    }

    public function test_add_rejects_non_transporter_type(): void
    {
        $stable = $this->makeStable();
        $otherStable = $this->makeStable();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('transporter');

        app(TransportFavoriteManager::class)->add($stable, $otherStable);
    }

    public function test_add_rejects_unverified_transporter(): void
    {
        $stable = $this->makeStable();
        $transporter = $this->makeTransporter(VerificationStatus::Pending);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unverified');

        app(TransportFavoriteManager::class)->add($stable, $transporter);
    }

    public function test_add_is_idempotent_returns_false_on_duplicate(): void
    {
        $stable = $this->makeStable();
        $transporter = $this->makeTransporter(VerificationStatus::Verified);
        $mgr = app(TransportFavoriteManager::class);

        $mgr->add($stable, $transporter);
        $second = $mgr->add($stable, $transporter);

        $this->assertFalse($second);
        $this->assertSame(1, TransportFavorite::count());
    }

    public function test_add_returns_false_when_limit_reached(): void
    {
        config()->set('transport.favorites.limit', 2);

        $stable = $this->makeStable();
        $mgr = app(TransportFavoriteManager::class);

        $t1 = $this->makeTransporter(VerificationStatus::Verified);
        $t2 = $this->makeTransporter(VerificationStatus::Verified);
        $t3 = $this->makeTransporter(VerificationStatus::Verified);

        $this->assertTrue($mgr->add($stable, $t1));
        $this->assertTrue($mgr->add($stable, $t2));
        $this->assertFalse($mgr->add($stable, $t3));   // limit hit

        $this->assertSame(2, TransportFavorite::count());
    }

    public function test_remove_is_idempotent(): void
    {
        $stable = $this->makeStable();
        $transporter = $this->makeTransporter(VerificationStatus::Verified);
        $mgr = app(TransportFavoriteManager::class);

        $mgr->add($stable, $transporter);
        $mgr->remove($stable, $transporter->id);
        $mgr->remove($stable, $transporter->id);   // second call no-op

        $this->assertSame(0, TransportFavorite::count());
    }

    public function test_is_favorite_reflects_state(): void
    {
        $stable = $this->makeStable();
        $transporter = $this->makeTransporter(VerificationStatus::Verified);
        $mgr = app(TransportFavoriteManager::class);

        $this->assertFalse($mgr->isFavorite($stable, $transporter->id));
        $mgr->add($stable, $transporter);
        $this->assertTrue($mgr->isFavorite($stable, $transporter->id));
        $mgr->remove($stable, $transporter->id);
        $this->assertFalse($mgr->isFavorite($stable, $transporter->id));
    }

    public function test_available_query_only_returns_verified_transporters(): void
    {
        $verifiedTr = $this->makeTransporter(VerificationStatus::Verified);
        $pendingTr = $this->makeTransporter(VerificationStatus::Pending);
        $stable = $this->makeStable();

        $ids = app(TransportFavoriteManager::class)->availableTransportersQuery()->pluck('id')->all();

        $this->assertContains($verifiedTr->id, $ids);
        $this->assertNotContains($pendingTr->id, $ids);
        $this->assertNotContains($stable->id, $ids);
    }

    public function test_filament_page_route_registered(): void
    {
        $names = collect(app('router')->getRoutes())->map(fn ($r) => $r->getName())->filter()->values();
        $this->assertTrue($names->contains('filament.app.pages.transport-favorites'));
    }

    private function makeStable(): Tenant
    {
        return Tenant::create([
            'slug' => 'stajnia-'.uniqid(),
            'name' => 'Stajnia',
            'type' => TenantType::Stable,
            'db_name' => 's_'.uniqid(),
            'db_username' => 's_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }

    private function makeTransporter(VerificationStatus $vs): Tenant
    {
        return Tenant::create([
            'slug' => 't-'.uniqid(),
            'name' => 'Firma',
            'type' => TenantType::Transporter,
            'verification_status' => $vs,
            'db_name' => 't_'.uniqid(),
            'db_username' => 't_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }
}
