<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Jobs\Billing\ChargeRecurringPayUSubscriptionsJob;
use App\Jobs\Owner\GenerateMonthlyBoardingInvoicesJob;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * Regression guard dla scheduler'a. Każda pozycja na liście to komenda
 * krytyczna do działania produkcji — jeśli ktoś usunie lub przemianuje
 * z routes/console.php bez aktualizacji testu, suite się wywali.
 *
 * Lista zsynchronizowana z routes/console.php — przy dodawaniu nowych
 * schedules zaktualizuj `EXPECTED_COMMANDS`.
 */
class ScheduleRegistrationTest extends TestCase
{
    private const EXPECTED_COMMANDS = [
        'bookings:send-reminders',
        'tenants:snapshot-health',
        'hovera:demo:reset',
        'transport:dispatch-review-invites',
        'transporter:docs-expiry-notify',
        'transport:ksef:poll-submitted',
        'ksef:poll-tenant-invoices',
        'transport:expire-featured',
        'transport:scrape-fuel',
        'health-records:remind-due',
    ];

    /**
     * Lista krytycznych jobów (klasy queue jobs, nie artisan commands).
     * Schedule::job() rejestruje CallbackEvent (a nie command), więc
     * sprawdzamy je przez inny mechanizm niż command list.
     */
    private const EXPECTED_JOBS = [
        GenerateMonthlyBoardingInvoicesJob::class,
        ChargeRecurringPayUSubscriptionsJob::class,
    ];

    public function test_all_critical_commands_are_scheduled(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $registered = collect($schedule->events())
            ->map(fn ($event) => $event->command)
            ->filter()
            ->map(fn (string $cmd): string => $this->extractArtisanCommand($cmd))
            ->filter()
            ->values()
            ->all();

        foreach (self::EXPECTED_COMMANDS as $expected) {
            $this->assertContains(
                $expected,
                $registered,
                "Schedule for `{$expected}` is missing from routes/console.php"
            );
        }
    }

    public function test_all_critical_jobs_are_scheduled(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $descriptions = collect($schedule->events())
            ->map(fn ($event) => $event->getSummaryForDisplay())
            ->all();

        foreach (self::EXPECTED_JOBS as $jobClass) {
            $found = collect($descriptions)->contains(fn (string $d) => str_contains($d, $jobClass));
            $this->assertTrue(
                $found,
                "Schedule for job `{$jobClass}` is missing from routes/console.php"
            );
        }
    }

    private function extractArtisanCommand(string $rawCommand): ?string
    {
        // Event->command wygląda tak: "'/usr/bin/php8' 'artisan' bookings:send-reminders".
        if (preg_match("/'artisan'\s+(\S+)/", $rawCommand, $m)) {
            return $m[1];
        }

        return null;
    }
}
