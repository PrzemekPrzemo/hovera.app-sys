<?php

declare(strict_types=1);

namespace App\Services\Internal;

use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Models\Tenant\InternalChannel;
use App\Models\Tenant\InternalChannelMember;
use App\Models\Tenant\InternalMessage;
use App\Tenancy\TenantManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Logika Channel C (PR O5 epic 2) — seedowanie domyślnych kanałów,
 * publikowanie wiadomości i ekstrakcja @mention.
 *
 * Wszystkie metody działają w kontekście aktualnego tenanta (TenantManager)
 * — kanały żyją w tenant DB, a lista członków pochodzi z central
 * memberships przefiltrowanych do bieżącej stajni.
 */
class InternalChannelService
{
    /**
     * Domyślne kanały tworzone przy zakończeniu onboardingu (per captured
     * decisions §4 — hybrid: 3 auto + admin może dodać kolejne).
     *
     * @var list<array{slug:string,name:string,description:string}>
     */
    public const DEFAULT_CHANNELS = [
        ['slug' => 'general', 'name' => 'general', 'description' => 'Ogólny kanał stajni'],
        ['slug' => 'weterynaria', 'name' => 'weterynaria', 'description' => 'Sprawy weterynaryjne'],
        ['slug' => 'transport', 'name' => 'transport', 'description' => 'Organizacja transportu'],
    ];

    public function __construct(private readonly TenantManager $tenants) {}

    /**
     * Idempotentnie tworzy domyślne kanały dla bieżącej stajni i zapisuje
     * w nich wszystkich aktywnych członków. Bezpieczne do wielokrotnego
     * wywołania (np. ponowny onboarding finish albo lazy-ensure).
     *
     * @return int liczba nowo utworzonych kanałów
     */
    public function ensureDefaultsFor(): int
    {
        // Defensywnie: jeśli migracja kanałów jeszcze nie przeszła dla tej
        // stajni, nie wysadzamy onboarding finish.
        if (! Schema::connection('tenant')->hasTable('internal_channels')) {
            return 0;
        }

        $memberIds = $this->activeMemberIds();
        $created = 0;

        foreach (self::DEFAULT_CHANNELS as $definition) {
            $channel = InternalChannel::query()->where('slug', $definition['slug'])->first();

            if ($channel === null) {
                $channel = InternalChannel::create([
                    'slug' => $definition['slug'],
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'is_default' => true,
                ]);
                $created++;
            }

            $this->addMembers($channel, $memberIds);
        }

        return $created;
    }

    /**
     * Dodaje do kanału członków, których jeszcze w nim nie ma.
     *
     * @param list<string> $userIds
     */
    public function addMembers(InternalChannel $channel, array $userIds): void
    {
        $existing = $channel->members()->pluck('user_id')->all();

        foreach (array_diff($userIds, $existing) as $userId) {
            InternalChannelMember::create([
                'channel_id' => $channel->id,
                'user_id' => $userId,
                'joined_at' => now(),
                'notifications_enabled' => true,
            ]);
        }
    }

    /**
     * Dodaje do kanału wszystkich aktywnych członków stajni (używane przy
     * tworzeniu kanału przez admina).
     */
    public function addAllActiveMembers(InternalChannel $channel): void
    {
        $this->addMembers($channel, $this->activeMemberIds());
    }

    /**
     * Oznacza kanał jako przeczytany dla danego usera (bumpuje last_read_at).
     */
    public function markChannelRead(InternalChannel $channel, string $userId): void
    {
        InternalChannelMember::query()
            ->where('channel_id', $channel->id)
            ->where('user_id', $userId)
            ->update(['last_read_at' => now()]);
    }

    /**
     * Liczba nieprzeczytanych wiadomości w kanale dla danego usera (po
     * last_read_at, z pominięciem własnych).
     */
    public function unreadCount(InternalChannel $channel, string $userId): int
    {
        $member = InternalChannelMember::query()
            ->where('channel_id', $channel->id)
            ->where('user_id', $userId)
            ->first();

        if ($member === null) {
            return 0;
        }

        return InternalMessage::query()
            ->where('channel_id', $channel->id)
            ->where('author_user_id', '!=', $userId)
            ->when($member->last_read_at !== null, fn ($q) => $q->where('created_at', '>', $member->last_read_at))
            ->count();
    }

    /**
     * Publikuje wiadomość w kanale, wyłuskując @mention z treści.
     *
     * @param array<int,array<string,mixed>> $attachments
     */
    public function postMessage(
        InternalChannel $channel,
        string $authorUserId,
        string $body,
        array $attachments = [],
    ): InternalMessage {
        $mentions = $this->extractMentions($body);

        return InternalMessage::create([
            'channel_id' => $channel->id,
            'author_user_id' => $authorUserId,
            'body' => $body,
            'attachments' => $attachments === [] ? null : $attachments,
            'mentions' => $mentions === [] ? null : $mentions,
        ]);
    }

    /**
     * Wyłuskuje central user id ze wzmianek `@handle` w treści. Handle =
     * lokalna część e-maila (przed @) albo slug imienia i nazwiska bez
     * separatorów. Dopasowanie case-insensitive, zwraca unikalne id.
     *
     * @return list<string>
     */
    public function extractMentions(string $body): array
    {
        preg_match_all('/@([a-z0-9._-]+)/i', $body, $matches);
        $tokens = array_map('mb_strtolower', $matches[1] ?? []);

        if ($tokens === []) {
            return [];
        }

        $resolved = [];
        foreach ($this->mentionableUsers() as $user) {
            foreach ($this->handlesFor($user) as $handle) {
                if (in_array($handle, $tokens, true)) {
                    $resolved[$user->id] = $user->id;
                    break;
                }
            }
        }

        return array_values($resolved);
    }

    /**
     * Aktywni członkowie bieżącej stajni jako kandydaci do @mention.
     *
     * @return Collection<int,User>
     */
    public function mentionableUsers()
    {
        return User::query()
            ->whereIn('id', $this->activeMemberIds())
            ->get(['id', 'name', 'email']);
    }

    /**
     * Możliwe handle dla usera (e-mail local-part + slug imienia).
     *
     * @return list<string>
     */
    private function handlesFor(User $user): array
    {
        $handles = [];

        $local = Str::before((string) $user->email, '@');
        if ($local !== '') {
            $handles[] = mb_strtolower($local);
        }

        $slug = Str::slug((string) $user->name, '');
        if ($slug !== '') {
            $handles[] = $slug;
        }

        return array_values(array_unique($handles));
    }

    /**
     * Id aktywnych central users powiązanych z bieżącą stajnią.
     *
     * @return list<string>
     */
    private function activeMemberIds(): array
    {
        $tenant = $this->tenants->current();
        if ($tenant === null) {
            return [];
        }

        return TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->whereNull('revoked_at')
            ->pluck('user_id')
            ->all();
    }
}
