<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Transport\Reviews\TransportReviewInviteService;
use Illuminate\Console\Command;

/**
 * Cron entrypoint dla generatora zaproszeń do recenzji. Patrz
 * docs/TRANSPORT.md §12. Schedule = daily 09:00 Warsaw (zob.
 * routes/console.php).
 */
class DispatchTransportReviewInvitesCommand extends Command
{
    protected $signature = 'transport:dispatch-review-invites';

    protected $description = 'Wysyła zaproszenia do recenzji 14 dni po preferred_date dla zaakceptowanych ofert.';

    public function handle(TransportReviewInviteService $service): int
    {
        $count = $service->dispatchPendingInvites();
        $this->info("Wysłano {$count} zaproszeń do recenzji.");

        return self::SUCCESS;
    }
}
