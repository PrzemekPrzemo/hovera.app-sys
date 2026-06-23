<?php

declare(strict_types=1);

namespace Tests\Feature\Specialist;

use App\Filament\Specialist\Resources\OwnerThreadResource\Pages\ListOwnerThreads;
use App\Models\Central\ExternalSpecialist;
use App\Models\Central\OwnerSpecialistThread;
use App\Models\Central\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * PR O5 Channel D (epic 3) — specialist owner-inbox pokazuje tylko wątki
 * zalogowanego specjalisty.
 */
class OwnerThreadInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_inbox_lists_only_own_threads(): void
    {
        $owner = User::create(['name' => 'Olga', 'email' => 'olga@example.com', 'password' => bcrypt('x')]);

        $me = ExternalSpecialist::create([
            'email' => 'me@example.com',
            'display_name' => 'dr Me',
            'specialty' => 'vet',
            'password_hash' => Hash::make('haslo-12345'),
            'email_verified_at' => now(),
        ]);
        $other = ExternalSpecialist::create([
            'email' => 'other@example.com',
            'display_name' => 'dr Other',
            'specialty' => 'vet',
        ]);

        $mine = OwnerSpecialistThread::create([
            'owner_user_id' => $owner->id,
            'specialist_id' => $me->id,
            'subject' => 'Mój wątek',
        ]);
        $theirs = OwnerSpecialistThread::create([
            'owner_user_id' => $owner->id,
            'specialist_id' => $other->id,
            'subject' => 'Cudzy wątek',
        ]);

        $this->actingAs($me, 'specialist');
        Filament::setCurrentPanel(Filament::getPanel('specialist'));

        Livewire::test(ListOwnerThreads::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }
}
