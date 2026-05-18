<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Reviews;

use App\Filament\Admin\Resources\TransportReviewResource as AdminResource;
use App\Filament\Transport\Resources\TransportReviewResource as TransportResource;
use App\Models\Central\TransportReview;
use Tests\TestCase;

/**
 * Lightweight: sprawdzamy że oba Resource'y są wgrane jako klasy i mają
 * podpiętą model class. Pełne testy interakcji Filament (login + visit
 * panel) wymagałyby user + tenant setup wykraczający poza in-memory sqlite
 * skeleton tej fazy.
 */
class TransportReviewFilamentTest extends TestCase
{
    public function test_transport_panel_resource_registered(): void
    {
        $this->assertTrue(class_exists(TransportResource::class));
        $this->assertSame(TransportReview::class, TransportResource::getModel());
    }

    public function test_admin_panel_resource_registered(): void
    {
        $this->assertTrue(class_exists(AdminResource::class));
        $this->assertSame(TransportReview::class, AdminResource::getModel());
    }
}
