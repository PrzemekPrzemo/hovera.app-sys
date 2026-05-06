<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Central\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds the first master admin user.
 *
 * Email is fixed (per project requirements). Password is generated
 * randomly and printed once — operator must reset it via the
 * password-reset flow on first login.
 *
 * Idempotent: re-running this seeder will only create the user the
 * first time, then become a no-op.
 */
class MasterAdminSeeder extends Seeder
{
    private const ADMIN_EMAIL = 'przemek@szulecki.pl';

    public function run(): void
    {
        $existing = User::where('email', self::ADMIN_EMAIL)->first();

        if ($existing) {
            $existing->forceFill(['is_master_admin' => true])->save();
            $this->command->info("Master admin already exists: {$existing->email}");
            return;
        }

        $password = Str::password(20, symbols: false);

        $user = User::create([
            'email'           => self::ADMIN_EMAIL,
            'name'            => 'Przemek Szulecki',
            'password'        => Hash::make($password),
            'locale'          => 'pl',
            'timezone'        => 'Europe/Warsaw',
            'is_master_admin' => true,
            'email_verified_at' => now(),
        ]);

        $this->command->warn('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->warn(' MASTER ADMIN CREATED — record the password NOW');
        $this->command->warn('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info("  Email:    {$user->email}");
        $this->command->info("  Password: {$password}");
        $this->command->warn('  This password is shown once. Reset it after first login.');
        $this->command->warn('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }
}
