<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Notifications\Boarding\HorseBoardingAcceptedNotification;
use App\Notifications\Boarding\HorseBoardingRejectedNotification;
use App\Notifications\Boarding\HorseBoardingRequestedNotification;
use Tests\TestCase;

/**
 * Pokrywa boarding notification classes (PR 5 z TODO.md):
 *   - HorseBoardingRequestedNotification — stable wysłał, owner dostaje
 *   - HorseBoardingAcceptedNotification — owner zaakceptował, stable dostaje
 *   - HorseBoardingRejectedNotification — owner odrzucił + reason, stable dostaje
 *
 * Test sprawdza že notifications mają poprawny shape (database payload
 * + mail subject/lines/action) bez bootowania pełnego Laravel notification
 * dispatcher'a — czyste unit pokrycie metod `toDatabase` + `toMail`.
 */
class BoardingNotificationsTest extends TestCase
{
    public function test_boarding_requested_notification_database_payload(): void
    {
        $n = new HorseBoardingRequestedNotification(
            assignmentId: '01ASSIGNXXXX',
            stableTenantId: '01STABLEXXXX',
            stableName: 'Stajnia Iskra',
            centralHorseId: '01HORSEXXXXX',
            horseName: 'Pegaz',
            ownerPanelUrl: 'https://hovera.app/owner/pending-boarding-requests',
        );

        $payload = $n->toDatabase(null);

        $this->assertSame('boarding.requested', $payload['kind']);
        $this->assertSame('01ASSIGNXXXX', $payload['assignment_id']);
        $this->assertSame('Stajnia Iskra', $payload['stable_name']);
        $this->assertSame('Pegaz', $payload['horse_name']);
        $this->assertStringContainsString('/owner/pending-boarding-requests', $payload['url']);
    }

    public function test_boarding_requested_notification_mail_message(): void
    {
        $n = new HorseBoardingRequestedNotification(
            assignmentId: '01A',
            stableTenantId: '01S',
            stableName: 'Stajnia Iskra',
            centralHorseId: '01H',
            horseName: 'Pegaz',
            ownerPanelUrl: 'https://hovera.app/owner/pending-boarding-requests',
        );

        $mail = $n->toMail(null);

        $this->assertStringContainsString('Pegaz', $mail->subject);
        $this->assertStringContainsString('Stajnia Iskra', $mail->subject);
        $intro = implode("\n", $mail->introLines);
        $this->assertStringContainsString('Stajnia Iskra', $intro);
        $this->assertStringContainsString('Pegaz', $intro);
        $this->assertNotEmpty($mail->actionUrl);
    }

    public function test_boarding_accepted_notification_payload(): void
    {
        $n = new HorseBoardingAcceptedNotification(
            assignmentId: '01A',
            ownerName: 'Jan Owner',
            ownerEmail: 'jan@example.test',
            centralHorseId: '01H',
            horseName: 'Pegaz',
            stableHorseUrl: 'https://hovera.app/app/horses',
        );

        $payload = $n->toDatabase(null);
        $this->assertSame('boarding.accepted', $payload['kind']);
        $this->assertSame('Jan Owner', $payload['owner_name']);

        $mail = $n->toMail(null);
        $this->assertStringContainsString('Pegaz', $mail->subject);
        $this->assertStringContainsString('Jan Owner', implode("\n", $mail->introLines));
    }

    public function test_boarding_rejected_notification_includes_reason_and_contact(): void
    {
        $n = new HorseBoardingRejectedNotification(
            assignmentId: '01A',
            ownerName: 'Jan Owner',
            ownerEmail: 'jan@example.test',
            centralHorseId: '01H',
            horseName: 'Pegaz',
            reason: 'Już sprzedałem konia, nie potrzebuję boardingu',
        );

        $payload = $n->toDatabase(null);
        $this->assertSame('boarding.rejected', $payload['kind']);
        $this->assertSame('Już sprzedałem konia, nie potrzebuję boardingu', $payload['reason']);

        $mail = $n->toMail(null);
        $lines = implode("\n", $mail->introLines);
        $this->assertStringContainsString('Jan Owner', $lines);
        $this->assertStringContainsString('Pegaz', $lines);
        $this->assertStringContainsString('sprzedałem', $lines);
        $this->assertStringContainsString('jan@example.test', $lines);
    }

    public function test_all_notifications_use_database_and_mail_channels(): void
    {
        $requested = new HorseBoardingRequestedNotification('a', 's', 'S', 'h', 'H', 'u');
        $accepted = new HorseBoardingAcceptedNotification('a', 'o', 'oe', 'h', 'H', 'u');
        $rejected = new HorseBoardingRejectedNotification('a', 'o', 'oe', 'h', 'H', 'r');

        foreach ([$requested, $accepted, $rejected] as $n) {
            $channels = $n->via(null);
            $this->assertContains('database', $channels);
            $this->assertContains('mail', $channels);
        }
    }
}
