<?php

declare(strict_types=1);

namespace Tests\Feature\Owner;

use App\Filament\Owner\Resources\NotificationResource;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PR O1 — Owner notifications hub. Pokrywa scope query (per-user) + badge
 * count + mark-as-read flow przez resource (bez Livewire, na poziomie
 * service/eloquent-level).
 */
class NotificationResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_eloquent_query_scoped_to_authenticated_user(): void
    {
        $userA = $this->makeUser();
        $userB = $this->makeUser();

        $this->seedNotification($userA, ['title' => 'Dla A']);
        $this->seedNotification($userA, ['title' => 'Druga dla A']);
        $this->seedNotification($userB, ['title' => 'Dla B']);

        $this->actingAs($userA);
        $countA = NotificationResource::getEloquentQuery()->count();

        $this->actingAs($userB);
        $countB = NotificationResource::getEloquentQuery()->count();

        $this->assertSame(2, $countA);
        $this->assertSame(1, $countB);
    }

    public function test_eloquent_query_returns_empty_when_no_user_authenticated(): void
    {
        $count = NotificationResource::getEloquentQuery()->count();
        $this->assertSame(0, $count);
    }

    public function test_navigation_badge_shows_unread_count_only(): void
    {
        $user = $this->makeUser();
        $this->seedNotification($user, ['title' => 'Unread A']);
        $this->seedNotification($user, ['title' => 'Unread B']);

        $readOne = $this->seedNotification($user, ['title' => 'Read']);
        $readOne->markAsRead();

        $this->actingAs($user);

        $this->assertSame('2', NotificationResource::getNavigationBadge());
    }

    public function test_navigation_badge_returns_null_when_all_read(): void
    {
        $user = $this->makeUser();
        $n = $this->seedNotification($user, ['title' => 'Read']);
        $n->markAsRead();

        $this->actingAs($user);

        $this->assertNull(NotificationResource::getNavigationBadge());
    }

    public function test_mark_as_read_updates_read_at(): void
    {
        $user = $this->makeUser();
        $n = $this->seedNotification($user, ['title' => 'X']);

        $this->assertNull($n->read_at);

        $n->markAsRead();
        $n->refresh();

        $this->assertNotNull($n->read_at);
    }

    public function test_user_cannot_create_notifications(): void
    {
        $this->assertFalse(NotificationResource::canCreate());
    }

    private function makeUser(): User
    {
        return User::create([
            'email' => 'owner-'.uniqid().'@example.com',
            'name' => 'Owner '.uniqid(),
            'password' => Hash::make('secret'),
        ]);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function seedNotification(User $user, array $data): DatabaseNotification
    {
        $notification = new DatabaseNotification([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\Owner\\TestNotification',
            'notifiable_type' => $user::class,
            'notifiable_id' => $user->getKey(),
            'data' => $data,
        ]);
        $notification->setConnection($user->getConnectionName());
        $notification->save();

        return $notification;
    }
}
