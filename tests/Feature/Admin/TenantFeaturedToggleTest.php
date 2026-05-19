<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Manualny "Polecany" boost dla rankingowania. Tenant model helpers
 * + idempotencja toggle'a. Pełny Filament action covered w wider integration
 * test (rejestracja akcji w table, render z confirmation modal'em) —
 * tutaj tylko model layer i scope.
 */
class TenantFeaturedToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_featured_sets_flag_timestamp_and_user(): void
    {
        $tenant = $this->makeTransporter()->fresh();
        $this->assertFalse($tenant->is_featured);

        $tenant->markFeatured('01HXY00000000000000000000Z');

        $tenant->refresh();
        $this->assertTrue($tenant->is_featured);
        $this->assertNotNull($tenant->featured_at);
        $this->assertSame('01HXY00000000000000000000Z', $tenant->featured_by_user_id);
    }

    public function test_mark_featured_is_idempotent(): void
    {
        $tenant = $this->makeTransporter();
        $tenant->markFeatured('user-a');
        $featuredAt = $tenant->fresh()->featured_at;

        // Second call powinien być no-op — featured_at zostaje stare.
        sleep(1);
        $tenant->markFeatured('user-b');

        $tenant->refresh();
        $this->assertTrue($tenant->is_featured);
        $this->assertEquals($featuredAt->toDateTimeString(), $tenant->featured_at->toDateTimeString());
    }

    public function test_unmark_featured_clears_flag_timestamp_and_user(): void
    {
        $tenant = $this->makeTransporter();
        $tenant->markFeatured('user-a');

        $tenant->unmarkFeatured();

        $tenant->refresh();
        $this->assertFalse($tenant->is_featured);
        $this->assertNull($tenant->featured_at);
        $this->assertNull($tenant->featured_by_user_id);
    }

    public function test_scope_featured_filters_to_flagged_only(): void
    {
        $featured = $this->makeTransporter('featured-co');
        $regular = $this->makeTransporter('regular-co');

        $featured->markFeatured();

        $ids = Tenant::query()->featured()->pluck('id')->all();

        $this->assertContains($featured->id, $ids);
        $this->assertNotContains($regular->id, $ids);
    }

    private function makeTransporter(?string $slug = null): Tenant
    {
        return Tenant::create([
            'slug' => $slug ?? 't-'.uniqid(),
            'name' => 'Firma',
            'type' => TenantType::Transporter,
            'verification_status' => VerificationStatus::Verified,
            'db_name' => 't_'.uniqid(),
            'db_username' => 't_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }
}
