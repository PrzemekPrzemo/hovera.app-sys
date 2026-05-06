<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\Profile;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Profile page is the same Filament class registered in two panels.
 * Livewire renders it directly here, which is enough to exercise the
 * `save` / `changePassword` methods without going through the panel
 * routing.
 */
class ProfilePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_updates_basic_fields(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->set('data.name', 'Nowe Imię')
            ->set('data.locale', 'en')
            ->set('data.timezone', 'Europe/Berlin')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('Nowe Imię', $user->name);
        $this->assertSame('en', $user->locale);
        $this->assertSame('Europe/Berlin', $user->timezone);
    }

    public function test_save_does_not_change_email(): void
    {
        $user = $this->makeUser();
        $original = $user->email;
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->set('data.name', 'Whatever')
            ->set('data.locale', 'pl')
            ->set('data.timezone', 'Europe/Warsaw')
            ->call('save');

        $this->assertSame($original, $user->refresh()->email);
    }

    public function test_change_password_requires_correct_current_password(): void
    {
        $user = $this->makeUser('correct_pw_123');
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->set('passwordData.current_password', 'WRONG')
            ->set('passwordData.password', 'BrandNewPassword123!')
            ->set('passwordData.password_confirmation', 'BrandNewPassword123!')
            ->call('changePassword');

        // Hash unchanged
        $this->assertTrue(Hash::check('correct_pw_123', $user->refresh()->password));
    }

    public function test_change_password_succeeds_with_correct_current_password(): void
    {
        $user = $this->makeUser('correct_pw_123');
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->set('passwordData.current_password', 'correct_pw_123')
            ->set('passwordData.password', 'BrandNewPassword123!')
            ->set('passwordData.password_confirmation', 'BrandNewPassword123!')
            ->call('changePassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('BrandNewPassword123!', $user->refresh()->password));
    }

    public function test_change_password_validates_minimum_length(): void
    {
        $user = $this->makeUser('correct_pw_123');
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->set('passwordData.current_password', 'correct_pw_123')
            ->set('passwordData.password', 'short')
            ->set('passwordData.password_confirmation', 'short')
            ->call('changePassword')
            ->assertHasErrors(['passwordData.password']);
    }

    public function test_change_password_validates_match(): void
    {
        $user = $this->makeUser('correct_pw_123');
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->set('passwordData.current_password', 'correct_pw_123')
            ->set('passwordData.password', 'longEnoughPassword123')
            ->set('passwordData.password_confirmation', 'differentPassword456')
            ->call('changePassword')
            ->assertHasErrors(['passwordData.password']);
    }

    private function makeUser(string $password = 'whatever_password'): User
    {
        return User::create([
            'email' => 'profile@example.com',
            'name' => 'Profile User',
            'password' => Hash::make($password),
            'locale' => 'pl',
            'timezone' => 'Europe/Warsaw',
        ]);
    }
}
