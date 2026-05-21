<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\App\Resources\HorseResource\Pages\ListHorses;
use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pokrywa logikę lookup'u dla PR 1 — stable wpisuje email właściciela,
 * dostaje listę koni z central rejestru. Filament action sam jest
 * testowany manualnie (form input + reactive), tutaj testujemy
 * helper'y `resolveHorseOptions` + `renderLookupStatus`.
 */
class StableImportHorseFromRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_horse_options_empty_when_email_invalid(): void
    {
        $this->assertSame([], ListHorses::resolveHorseOptions(''));
        $this->assertSame([], ListHorses::resolveHorseOptions('not-an-email'));
    }

    public function test_resolve_horse_options_empty_when_user_not_found(): void
    {
        $this->assertSame([], ListHorses::resolveHorseOptions('ghost@example.test'));
    }

    public function test_resolve_horse_options_returns_user_horses_with_passport_label(): void
    {
        $owner = User::create([
            'name' => 'Jan',
            'email' => 'jan@example.test',
            'password' => bcrypt('x'),
        ]);

        $h1 = CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $owner->id,
            'name' => 'Iskra',
            'passport_no' => 'PL00012345',
        ]);
        $h2 = CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $owner->id,
            'name' => 'Brando',
            'passport_no' => null,  // brak paszportu
        ]);
        // Inny owner — nie powinien się pokazać
        $otherOwner = User::create([
            'name' => 'Other',
            'email' => 'other@example.test',
            'password' => bcrypt('x'),
        ]);
        CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $otherOwner->id,
            'name' => 'Pegaz',
        ]);

        $options = ListHorses::resolveHorseOptions('jan@example.test');

        $this->assertCount(2, $options);
        $this->assertSame('Brando ('.__('app/horse.action.import_from_registry.no_passport').')', $options[$h2->id]);
        $this->assertSame('Iskra (PL00012345)', $options[$h1->id]);
    }

    public function test_render_lookup_status_for_unknown_user(): void
    {
        $msg = ListHorses::renderLookupStatus('ghost@example.test');
        $this->assertSame(__('app/horse.action.import_from_registry.lookup.user_not_found'), $msg);
    }

    public function test_render_lookup_status_for_user_with_no_horses(): void
    {
        User::create([
            'name' => 'Empty',
            'email' => 'empty@example.test',
            'password' => bcrypt('x'),
        ]);

        $msg = ListHorses::renderLookupStatus('empty@example.test');
        $this->assertSame(
            __('app/horse.action.import_from_registry.lookup.no_horses', ['email' => 'empty@example.test']),
            $msg,
        );
    }

    public function test_render_lookup_status_counts_horses(): void
    {
        $owner = User::create([
            'name' => 'Multi',
            'email' => 'multi@example.test',
            'password' => bcrypt('x'),
        ]);
        CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $owner->id,
            'name' => 'A',
        ]);
        CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $owner->id,
            'name' => 'B',
        ]);

        $msg = ListHorses::renderLookupStatus('multi@example.test');
        $this->assertSame(
            __('app/horse.action.import_from_registry.lookup.found', ['count' => 2]),
            $msg,
        );
    }
}
