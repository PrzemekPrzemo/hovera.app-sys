<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Notifications\BookingConfirmedClientNotification;
use App\Notifications\BookingReminderClientNotification;
use App\Notifications\BookingRequestedClientNotification;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Quick-win sanity check: every customer-facing booking mail surfaces
 * the portal URL when one is provided, and stays silent when not.
 */
class BookingMailPortalLinkTest extends TestCase
{
    public function test_confirmed_mail_includes_portal_link(): void
    {
        $mail = (new BookingConfirmedClientNotification(
            tenantName: 'Stajnia Bucefał',
            startsAt: Carbon::parse('2026-06-01 10:00'),
            durationMinutes: 60,
            instructorName: 'Anna',
            horseName: null,
            arenaName: null,
            stableAddress: null,
            stablePhone: null,
            cancelUrl: 'https://app.hovera.app/cancel/x',
            cancellationPolicyHours: 12,
            portalUrl: 'https://app.hovera.app/s/buc/portal/login',
        ))->toMail(null);

        $body = implode("\n", $mail->introLines).implode("\n", $mail->outroLines);
        $this->assertStringContainsString('panelu klienta', $body);
        $this->assertStringContainsString('https://app.hovera.app/s/buc/portal/login', $body);
    }

    public function test_confirmed_mail_omits_portal_section_when_url_null(): void
    {
        $mail = (new BookingConfirmedClientNotification(
            tenantName: 'Stajnia',
            startsAt: Carbon::parse('2026-06-01 10:00'),
            durationMinutes: 60,
            instructorName: 'Anna',
            horseName: null, arenaName: null,
            stableAddress: null, stablePhone: null,
            cancelUrl: 'https://app/x',
            cancellationPolicyHours: 12,
        ))->toMail(null);

        $body = implode("\n", $mail->introLines).implode("\n", $mail->outroLines);
        $this->assertStringNotContainsString('panelu klienta', $body);
    }

    public function test_reminder_mail_includes_portal_link(): void
    {
        $mail = (new BookingReminderClientNotification(
            tenantName: 'Stajnia',
            startsAt: Carbon::parse('2026-06-01 10:00'),
            durationMinutes: 60,
            instructorName: 'Anna',
            horseName: null, arenaName: null,
            stableAddress: null, stablePhone: null,
            cancelUrl: 'https://app/x',
            cancellationPolicyHours: 12,
            portalUrl: 'https://app.hovera.app/s/buc/portal/login',
        ))->toMail(null);

        $body = implode("\n", $mail->introLines).implode("\n", $mail->outroLines);
        $this->assertStringContainsString('panelu klienta', $body);
        $this->assertStringContainsString('s/buc/portal/login', $body);
    }

    public function test_requested_mail_includes_portal_link(): void
    {
        $mail = (new BookingRequestedClientNotification(
            tenantName: 'Stajnia',
            startsAt: Carbon::parse('2026-06-01 10:00'),
            durationMinutes: 60,
            instructorName: 'Anna',
            cancelUrl: 'https://app/x',
            cancellationPolicyHours: 12,
            portalUrl: 'https://app.hovera.app/s/buc/portal/login',
        ))->toMail(null);

        $body = implode("\n", $mail->introLines).implode("\n", $mail->outroLines);
        $this->assertStringContainsString('panelu klienta', $body);
    }
}
