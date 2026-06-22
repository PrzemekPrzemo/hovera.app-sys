<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Central\SpecialistMagicLink;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Cleanup expired / old used magic links — chroni table przed bloat'em.
 *
 * SpecialistMagicLink::pruneExpired() usuwa:
 *   - expired > 7d ago (już niedostępne, retencja przez tydzień
 *     ułatwia debugowanie expired-link reportów od user'a)
 *   - used > 30d ago (audit retention — po miesiącu nie potrzebujemy)
 *
 * Scheduled daily 03:00 Europe/Warsaw (poza godzinami szczytu). Bez
 * lock'u — operacja idempotentna, dwie równoczesne instancje skończą
 * z tym samym wynikiem (DB DELETE jest atomic).
 */
class SpecialistsPruneMagicLinksCommand extends Command
{
    protected $signature = 'specialists:prune-magic-links';

    protected $description = 'Usuwa expired / old-used magic links specjalistów (cleanup table).';

    public function handle(): int
    {
        $pruned = SpecialistMagicLink::pruneExpired();

        $this->info(sprintf('Pruned %d magic links.', $pruned));
        Log::info('specialists.magic_links.pruned', ['count' => $pruned]);

        return self::SUCCESS;
    }
}
