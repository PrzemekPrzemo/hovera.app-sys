<?php

declare(strict_types=1);

namespace App\Filament\Owner\Resources\HorseResource\Pages;

use App\Domain\Horses\HorseRegistrySyncService;
use App\Filament\Owner\Resources\HorseResource;
use App\Models\Central\User;
use App\Models\Tenant\OwnerHorse;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateHorse extends CreateRecord
{
    protected static string $resource = HorseResource::class;

    /**
     * Po utworzeniu OwnerHorse (per-tenant DB), propagujemy do central
     * rejestru — tworzymy `central_horse_registry` row i back-fillsujemy
     * `owner_horse.central_horse_id`. Soft-fail: jeśli central call padnie,
     * lokalny rekord zostaje (owner i tak ma swojego konia w panelu),
     * a sync można uzupełnić później przez admin task.
     *
     * Patrz docs/MARKETPLACE-ROADMAP.md PR 4/5 §"Owner adds horse".
     */
    protected function afterCreate(): void
    {
        /** @var OwnerHorse $horse */
        $horse = $this->record;

        try {
            $owner = Auth::user();
            $user = $owner instanceof User ? $owner : null;

            app(HorseRegistrySyncService::class)->registerForOwner($horse, $user);
        } catch (Throwable $e) {
            Log::warning('Central horse registry sync failed', [
                'horse_id' => $horse->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
