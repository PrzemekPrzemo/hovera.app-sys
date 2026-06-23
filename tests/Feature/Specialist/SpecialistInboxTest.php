<?php

declare(strict_types=1);

namespace Tests\Feature\Specialist;

use App\Filament\Specialist\Resources\ThreadResource\Pages\ListThreads;
use App\Models\Central\ExternalSpecialist;
use App\Models\Central\SpecialistThread;
use App\Models\Central\Tenant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * PR O5 Channel B (epic 1.5) — specialist inbox pokazuje tylko wątki
 * zalogowanego specjalisty (scoping przez guard `specialist`).
 */
class SpecialistInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbox_lists_only_own_threads(): void
    {
        $tenant = $this->makeTenant();

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

        $mine = SpecialistThread::create([
            'tenant_id' => $tenant->id,
            'specialist_id' => $me->id,
            'subject' => 'Mój wątek',
        ]);
        $theirs = SpecialistThread::create([
            'tenant_id' => $tenant->id,
            'specialist_id' => $other->id,
            'subject' => 'Cudzy wątek',
        ]);

        $this->actingAs($me, 'specialist');
        Filament::setCurrentPanel(Filament::getPanel('specialist'));

        Livewire::test(ListThreads::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();
        $tenant = new Tenant([
            'slug' => 'inb-'.$u,
            'name' => 'Stajnia',
            'db_name' => 'inb_'.$u,
            'db_username' => 'inb_'.substr($u, -8),
            'status' => 'active',
            'settings' => [],
        ]);
        $tenant->db_password = 'irrelevant';
        $tenant->save();

        return $tenant;
    }
}
