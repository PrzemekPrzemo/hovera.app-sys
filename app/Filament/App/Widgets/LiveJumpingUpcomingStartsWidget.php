<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Models\Tenant\Client;
use App\Models\Tenant\Horse;
use App\Services\Integrations\LiveJumping\LiveJumpingClient;
use App\Services\Integrations\LiveJumping\LiveJumpingFeatureGate;
use App\Tenancy\TenantManager;
use Filament\Widgets\Widget;

/**
 * Lista nadchodzących startów z LiveJumping dla koni i jeźdźców
 * ze stajni, którzy mają wpisany URL profilu LJ. Widget pojawia się
 * na dashboardzie /app TYLKO gdy master admin włączył partnership.
 *
 * Renderowany przez własny blade — własna struktura listy z badge'ami
 * klasy zawodów i datą. Cache 5 min (zarządzany w LiveJumpingClient).
 */
class LiveJumpingUpcomingStartsWidget extends Widget
{
    protected static string $view = 'filament.app.widgets.livejumping-upcoming-starts';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return app(LiveJumpingFeatureGate::class)->enabled()
            && app(TenantManager::class)->hasTenant();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function getStarts(): array
    {
        if (! self::canView()) {
            return [];
        }

        $horseUrls = Horse::query()
            ->whereNotNull('livejumping_profile_url')
            ->where('livejumping_profile_url', '!=', '')
            ->pluck('livejumping_profile_url')
            ->all();

        $riderUrls = Client::query()
            ->whereNotNull('livejumping_profile_url')
            ->where('livejumping_profile_url', '!=', '')
            ->pluck('livejumping_profile_url')
            ->all();

        if ($horseUrls === [] && $riderUrls === []) {
            return [];
        }

        return app(LiveJumpingClient::class)->getUpcomingStarts(
            array_values($horseUrls),
            array_values($riderUrls),
        );
    }
}
