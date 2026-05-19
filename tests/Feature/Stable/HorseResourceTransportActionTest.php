<?php

declare(strict_types=1);

namespace Tests\Feature\Stable;

use App\Filament\App\Resources\HorseResource\Pages\EditHorse;
use Tests\TestCase;

/**
 * "Zamów transport" header action na karcie konia. Tu testujemy
 * konfigurację actions na poziomie Filament Page bez pełnego e2e
 * (HorseResource żyje per-tenant DB, co wymagałoby provisioned tenant
 * connection — poza scope tego PR-a).
 *
 * Verifikujemy że metoda getHeaderActions() jest defined and visible-callable.
 */
class HorseResourceTransportActionTest extends TestCase
{
    public function test_edit_horse_page_defines_order_transport_header_action(): void
    {
        $this->assertTrue(method_exists(EditHorse::class, 'getHeaderActions'));

        $reflection = new \ReflectionClass(EditHorse::class);
        $method = $reflection->getMethod('getHeaderActions');
        $this->assertTrue($method->isProtected());

        // Source-level check że action z 'order_transport' jest registered —
        // pełen render test wymagałby Filament panel + tenant context.
        $filename = $reflection->getFileName();
        $source = file_get_contents($filename);
        $this->assertStringContainsString("Actions\\Action::make('order_transport')", $source);
        $this->assertStringContainsString('canUseTransport()', $source);
        $this->assertStringContainsString('public.transport.inquiry', $source);
        $this->assertStringContainsString("\$params['stable'] = \$tenant->id;", $source);
        $this->assertStringContainsString("\$params['horse']", $source);
    }
}
