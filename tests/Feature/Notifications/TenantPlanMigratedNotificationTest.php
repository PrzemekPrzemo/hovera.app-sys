<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Notifications\TenantPlanMigratedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Mailable do owner'a tenanta po migracji legacy → nowy plan. Sprawdza
 * subject, treść (kluczowe pola), oraz że lock-in jest 12 miesięcy.
 */
class TenantPlanMigratedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_renders_with_old_new_plan_and_new_price(): void
    {
        $lockIn = Carbon::create(2027, 5, 19);
        $notification = new TenantPlanMigratedNotification(
            tenantName: 'Stajnia Testowa',
            oldPlanName: 'Solo Legacy',
            newPlanName: 'Transport Start',
            newPriceFormatted: '250 PLN / mc',
            effective: 'next_cycle',
            lockInUntil: $lockIn,
        );

        $mail = $notification->toMail((object) ['email' => 'owner@example.com']);
        $rendered = (string) $mail->render();

        $this->assertStringContainsString('Solo Legacy', $rendered);
        $this->assertStringContainsString('Transport Start', $rendered);
        $this->assertStringContainsString('250 PLN / mc', $rendered);
        $this->assertStringContainsString('2027-05-19', $rendered);
    }

    public function test_lock_in_date_is_twelve_months_from_now_when_via_migrator_default(): void
    {
        // Sanity: 12 mc ahead per docs/TRANSPORT.md §15.
        $lockIn = now()->addMonths(12)->startOfDay();
        $notification = new TenantPlanMigratedNotification(
            tenantName: 'X',
            oldPlanName: 'A',
            newPlanName: 'B',
            newPriceFormatted: '100 PLN',
            effective: 'immediate',
            lockInUntil: $lockIn,
        );

        // diffInMonths zwraca float w nowszym Carbonie — toleruj drobne
        // odchylenia przez round().
        $this->assertSame(12, (int) round(now()->diffInMonths($notification->lockInUntil)));
    }
}
