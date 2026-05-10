<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ApiTokensTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_admin_can_create_a_personal_access_token(): void
    {
        $admin = $this->makeAdmin();

        $newToken = $admin->createToken('Monitoring script', ['read-system'], now()->addDays(30));

        $this->assertNotEmpty($newToken->plainTextToken);
        $this->assertSame('Monitoring script', $newToken->accessToken->name);
        $this->assertSame(['read-system'], $newToken->accessToken->abilities);
        $this->assertNotNull($newToken->accessToken->expires_at);

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $newToken->accessToken->id,
            'tokenable_id' => $admin->id,
            'tokenable_type' => User::class,
            'name' => 'Monitoring script',
        ]);
    }

    public function test_master_admin_lists_only_own_tokens_via_query_scope(): void
    {
        $admin = $this->makeAdmin('a@example.com');
        $other = $this->makeAdmin('b@example.com');

        $admin->createToken('mine', ['read-system']);
        $other->createToken('not-mine', ['admin-all']);

        $rows = PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $admin->id)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame('mine', $rows->first()->name);
    }

    public function test_revoking_a_token_deletes_it(): void
    {
        $admin = $this->makeAdmin();
        $newToken = $admin->createToken('to-revoke', ['read-system']);

        $newToken->accessToken->delete();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $newToken->accessToken->id,
        ]);
    }

    public function test_plain_text_token_is_only_returned_once_at_issue_time(): void
    {
        $admin = $this->makeAdmin();
        $newToken = $admin->createToken('once', ['read-system']);

        // Plain-text token is recoverable in-memory exactly once via the
        // NewAccessToken returned by createToken — re-loading the row gives
        // only the hashed `token` column, never the plaintext.
        $reloaded = PersonalAccessToken::find($newToken->accessToken->id);
        $this->assertNotSame($newToken->plainTextToken, $reloaded->token);
    }

    private function makeAdmin(string $email = 'admin@example.com'): User
    {
        return User::create([
            'email' => $email,
            'name' => 'Admin',
            'password' => Hash::make('secret'),
            'is_master_admin' => true,
        ]);
    }
}
