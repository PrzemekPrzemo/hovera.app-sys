<?php

declare(strict_types=1);

namespace App\Domain\Transport\ServiceAreas;

use App\Models\Central\TransportServiceArea;
use App\Models\Central\Tenant;

/**
 * Zarządza listą województw, w których transporter operuje. Tabela
 * transport_service_areas (central, PR #192) jest multi-row per
 * (transporter_tenant_id, voivodeship). Tutaj sync API.
 */
class TransportServiceAreaManager
{
    /** @return list<string> nazwy 16 województw + null filter */
    public static function allVoivodeships(): array
    {
        return [
            'dolnośląskie', 'kujawsko-pomorskie', 'lubelskie', 'lubuskie',
            'łódzkie', 'małopolskie', 'mazowieckie', 'opolskie',
            'podkarpackie', 'podlaskie', 'pomorskie', 'śląskie',
            'świętokrzyskie', 'warmińsko-mazurskie', 'wielkopolskie', 'zachodniopomorskie',
        ];
    }

    /** @return list<string> voivodeships aktualnie ustawione dla transportera */
    public function listFor(Tenant $tenant): array
    {
        return TransportServiceArea::query()
            ->where('transporter_tenant_id', $tenant->id)
            ->pluck('voivodeship')
            ->all();
    }

    /**
     * Sync (idempotent) — bazuje na "wybrane teraz". Te brakujące insertuje,
     * te niepotrzebne usuwa. Pusta lista = transporter nie obsługuje żadnego
     * województwa (broadcast nigdy go nie znajdzie).
     *
     * @param  list<string>  $selected
     */
    public function sync(Tenant $tenant, array $selected): void
    {
        $allowed = array_intersect($selected, self::allVoivodeships());
        $current = $this->listFor($tenant);

        $toAdd = array_diff($allowed, $current);
        $toRemove = array_diff($current, $allowed);

        foreach ($toAdd as $voivodeship) {
            TransportServiceArea::query()->updateOrCreate(
                ['transporter_tenant_id' => $tenant->id, 'voivodeship' => $voivodeship],
                [],
            );
        }

        if (! empty($toRemove)) {
            TransportServiceArea::query()
                ->where('transporter_tenant_id', $tenant->id)
                ->whereIn('voivodeship', $toRemove)
                ->delete();
        }
    }

    /**
     * Returns voivodeships that the transporter would receive leads from in
     * broadcast mode — own selected + adjacent (config/transport.voivodeship_adjacency).
     *
     * @return list<string>
     */
    public function effectiveCoverage(Tenant $tenant): array
    {
        $own = $this->listFor($tenant);
        $adjacency = (array) config('transport.voivodeship_adjacency', []);

        $covered = $own;
        foreach ($own as $voivodeship) {
            foreach ((array) ($adjacency[$voivodeship] ?? []) as $neighbour) {
                $covered[] = $neighbour;
            }
        }

        return array_values(array_unique($covered));
    }
}
