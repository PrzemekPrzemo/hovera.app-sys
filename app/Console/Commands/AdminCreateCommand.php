<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Central\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class AdminCreateCommand extends Command
{
    protected $signature = 'hovera:admin:create
        {email : Master admin email}
        {name : Display name}
        {--password= : Password (prompted if missing)}
        {--update : Update existing user instead of failing}';

    protected $description = 'Create or update a master admin (idempotent helper for installer).';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $name = (string) $this->argument('name');
        $password = (string) ($this->option('password') ?: $this->secret('Password'));

        $validator = Validator::make([
            'email' => $email,
            'name' => $name,
            'password' => $password,
        ], [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $err) {
                $this->error($err);
            }

            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();
        if ($existing && ! $this->option('update')) {
            $this->error("User {$email} already exists. Pass --update to overwrite.");

            return self::FAILURE;
        }

        $user = $existing ?? new User;
        $user->fill([
            'email' => $email,
            'name' => $name,
            'password' => $password,
            'is_master_admin' => true,
            'locale' => 'pl',
            'timezone' => config('app.timezone', 'Europe/Warsaw'),
        ]);
        $user->email_verified_at ??= now();
        $user->save();

        $this->info(($existing ? 'Updated' : 'Created').' master admin '.$email);

        return self::SUCCESS;
    }
}
