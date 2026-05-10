<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Exceptions\PlanLimitExceeded;
use App\Models\Central\Tenant;
use App\Models\Tenant\Client;
use App\Models\Tenant\Horse;

/**
 * Centralny enforcement limitów planu — używamy tego z Filament
 * resource page'ów (CreateHorse / CreateClient) żeby nie dać
 * stajni przekroczyć limit Pro-trial (10 koni / 5 klientów) ani
 * tym co siedzą na płatnych planach z hard-cap.
 *
 * `-1` w limicie = unlimited; brak klucza w limits = blokujemy
 * (zachowawczo) bo plan jest źle skonfigurowany.
 */
class PlanLimitChecker
{
    /**
     * Throws gdy dodanie kolejnego konia złamałoby limit. Liczy
     * łącznie z soft-deleted? Nie — soft-delete = realnie wolne
     * miejsce, więc liczymy tylko żywe rekordy.
     */
    public function assertCanAddHorse(Tenant $tenant): void
    {
        $limit = $tenant->effectiveLimits()['max_horses'] ?? 0;
        if ($limit < 0) {
            return; // unlimited
        }

        $current = Horse::query()->count();
        if ($current >= $limit) {
            throw PlanLimitExceeded::horses($limit);
        }
    }

    public function assertCanAddClient(Tenant $tenant): void
    {
        $limit = $tenant->effectiveLimits()['max_clients'] ?? 0;
        if ($limit < 0) {
            return;
        }

        $current = Client::query()->count();
        if ($current >= $limit) {
            throw PlanLimitExceeded::clients($limit);
        }
    }
}
