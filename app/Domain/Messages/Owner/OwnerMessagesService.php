<?php

declare(strict_types=1);

namespace App\Domain\Messages\Owner;

use App\Domain\Messages\Owner\Snapshots\HorseMessageSnapshot;
use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Tenant\Client;
use App\Models\Tenant\Horse;
use App\Models\Tenant\HorseMessage;
use App\Tenancy\TenantManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Cross-tenant serwis komunikacji Owner ↔ Stable. Owner pisze do stajni
 * w której boarduje jego koń; stajnia odpowiada (już istniejące UI w
 * stable panel'u). Wiadomości żyją w stable DB, scoped przez `horse_id`.
 *
 * Wszystkie metody używają TenantManager::execute żeby tymczasowo
 * przepiąć connection 'tenant' na stable DB. Po wyjściu mapujemy do
 * DTO (Eloquent niedostępny).
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 4".
 */
class OwnerMessagesService
{
    public function __construct(
        private readonly TenantManager $tenants,
    ) {}

    /**
     * Lista wiadomości dla konia (oba kierunki, ASC po sent_at — czytamy
     * od najstarszej do najnowszej, naturalny porządek chat'u).
     *
     * Sprawdza ownership (primary_owner w CentralHorseRegistry) i resolveuje
     * stable z active boarding'u. Per Q3 — gdy tylko ended boarding,
     * pozwalamy nadal czytać (historyczne wiadomości).
     *
     * @return list<HorseMessageSnapshot>
     *
     * @throws AuthorizationException gdy user nie jest owner'em konia
     * @throws RuntimeException gdy nie ma żadnego boarding'u (active/ended)
     */
    public function listForHorse(User $owner, string $centralHorseId): array
    {
        $this->ensureOwnership($owner, $centralHorseId);
        $stableTenant = $this->resolveStableTenant($owner, $centralHorseId);

        return $this->tenants->execute($stableTenant, function () use ($owner, $centralHorseId, $stableTenant): array {
            $horse = Horse::query()->where('central_horse_id', $centralHorseId)->first();
            if ($horse === null) {
                return [];
            }

            $client = Client::query()->where('central_user_id', $owner->id)->first();
            if ($client === null) {
                // Brak Client = brak wiadomości (owner jeszcze nie zlinkowany).
                return [];
            }

            $messages = HorseMessage::query()
                ->where('horse_id', $horse->id)
                ->where('client_id', $client->id)
                ->orderBy('sent_at')
                ->get();

            return array_map(fn (HorseMessage $m) => $this->mapToSnapshot($m, $stableTenant), $messages->all());
        });
    }

    /**
     * Owner wysyła wiadomość do stajni. direction='from_client',
     * sender_user_id=owner central id, client_id=Client.id w stable.
     *
     * @param  array<int, array{path: string, original_name?: string, mime?: string, size?: int}>  $attachments
     *
     * @throws AuthorizationException gdy nie ma active boarding'u (write
     *         wymaga active — ended pozwala tylko read per Q3)
     */
    public function send(
        User $owner,
        string $centralHorseId,
        ?string $subject,
        string $body,
        array $attachments = [],
    ): HorseMessageSnapshot {
        $this->ensureOwnership($owner, $centralHorseId);

        // Pisanie wymaga ACTIVE assignment'u (ended = read-only).
        $assignment = HorseBoardingAssignment::query()
            ->where('central_horse_id', $centralHorseId)
            ->where('owner_user_id', $owner->id)
            ->where('status', HorseBoardingAssignment::STATUS_ACTIVE)
            ->first();

        if ($assignment === null) {
            throw new AuthorizationException(
                __('owner/messages.access.send_requires_active_boarding')
            );
        }

        $stableTenant = Tenant::query()->find($assignment->stable_tenant_id);
        if ($stableTenant === null) {
            throw new RuntimeException("Stable tenant {$assignment->stable_tenant_id} not found");
        }

        return $this->tenants->execute($stableTenant, function () use ($owner, $centralHorseId, $subject, $body, $attachments, $stableTenant): HorseMessageSnapshot {
            $horse = Horse::query()->where('central_horse_id', $centralHorseId)->first();
            if ($horse === null) {
                throw new RuntimeException("Horse central_id={$centralHorseId} not found in stable DB");
            }

            $client = Client::query()->where('central_user_id', $owner->id)->first();
            if ($client === null) {
                // Auto-link: w przyszłości można dodać auto-create Client
                // dla ownera; teraz wymagamy żeby stable zaakceptował
                // boarding (co tworzy Client'a w tym samym flow).
                throw new RuntimeException(
                    'Client matching owner user_id not found — stable must finalize boarding first'
                );
            }

            $message = HorseMessage::create([
                'id' => (string) Str::ulid(),
                'horse_id' => $horse->id,
                'direction' => 'from_client',
                'sender_user_id' => $owner->id,
                'client_id' => $client->id,
                'subject' => $subject,
                'body' => $body,
                'attachments' => $attachments !== [] ? $attachments : null,
                'sent_at' => now(),
            ]);

            return $this->mapToSnapshot($message, $stableTenant);
        });
    }

    /**
     * Marker "przeczytane" dla wiadomości from_stable. No-op gdy wiadomość
     * jest from_client (owner nie może oznaczyć swojej wiadomości jako
     * przeczytanej) albo już oznaczona.
     *
     * @throws AuthorizationException gdy owner nie ma access (nie jego koń)
     */
    public function markRead(User $owner, string $stableTenantId, string $messageId): void
    {
        $stableTenant = Tenant::query()->find($stableTenantId);
        if ($stableTenant === null) {
            return; // silent — stale URL nie ujawnia istnienia stable
        }

        $this->tenants->execute($stableTenant, function () use ($owner, $messageId): void {
            $client = Client::query()->where('central_user_id', $owner->id)->first();
            if ($client === null) {
                return;
            }

            $message = HorseMessage::query()
                ->where('id', $messageId)
                ->where('client_id', $client->id)
                ->first();

            if ($message === null) {
                return;
            }

            // Owner może oznaczyć tylko wiadomości from_stable jako
            // przeczytane (z client side). Idempotent — jeśli już ustawione,
            // nie nadpisujemy timestamp'a.
            if ($message->direction !== 'from_stable') {
                return;
            }
            if ($message->read_by_client_at !== null) {
                return;
            }

            $message->forceFill(['read_by_client_at' => now()])->save();
        });
    }

    /**
     * Liczba nieprzeczytanych wiadomości od stajni dla owner'a (across all
     * stable'ów). Używane przez nav badge w owner panel'u.
     */
    public function unreadCount(User $owner): int
    {
        $tenantIds = HorseBoardingAssignment::query()
            ->where('owner_user_id', $owner->id)
            ->whereIn('status', [
                HorseBoardingAssignment::STATUS_ACTIVE,
                HorseBoardingAssignment::STATUS_ENDED,
            ])
            ->distinct()
            ->pluck('stable_tenant_id')
            ->all();

        $total = 0;
        foreach ($tenantIds as $tenantId) {
            $stableTenant = Tenant::query()->find($tenantId);
            if ($stableTenant === null) {
                continue;
            }
            $total += $this->tenants->execute($stableTenant, function () use ($owner): int {
                $client = Client::query()->where('central_user_id', $owner->id)->first();
                if ($client === null) {
                    return 0;
                }

                return HorseMessage::query()
                    ->where('client_id', $client->id)
                    ->where('direction', 'from_stable')
                    ->whereNull('read_by_client_at')
                    ->count();
            });
        }

        return $total;
    }

    /**
     * Krok bezpieczeństwa — sprawdza że user jest primary_owner w
     * CentralHorseRegistry. Throws gdy nie. Szersze niż gate.authorize()
     * (nie wymaga ACTIVE boarding'u — ended pozwala read).
     *
     * @throws AuthorizationException
     */
    private function ensureOwnership(User $owner, string $centralHorseId): void
    {
        $exists = CentralHorseRegistry::query()
            ->where('id', $centralHorseId)
            ->where('primary_owner_user_id', $owner->id)
            ->exists();

        if (! $exists) {
            throw new AuthorizationException(__('owner/messages.access.not_owner'));
        }
    }

    /**
     * Resolve stable tenant z assignment'u (active priority, fallback ended).
     */
    private function resolveStableTenant(User $owner, string $centralHorseId): Tenant
    {
        $assignment = HorseBoardingAssignment::query()
            ->where('central_horse_id', $centralHorseId)
            ->where('owner_user_id', $owner->id)
            ->whereIn('status', [
                HorseBoardingAssignment::STATUS_ACTIVE,
                HorseBoardingAssignment::STATUS_ENDED,
            ])
            ->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")  // active first
            ->orderByDesc('started_at')
            ->first();

        if ($assignment === null) {
            throw new RuntimeException(
                "No boarding assignment found for horse {$centralHorseId} and owner {$owner->id}"
            );
        }

        $tenant = Tenant::query()->find($assignment->stable_tenant_id);
        if ($tenant === null) {
            throw new RuntimeException("Stable tenant {$assignment->stable_tenant_id} not found");
        }

        return $tenant;
    }

    private function mapToSnapshot(HorseMessage $message, Tenant $stableTenant): HorseMessageSnapshot
    {
        // Sender name resolution — dla from_stable z sender_user_id pull
        // z User central. Dla from_client = klient (Client.name).
        $senderName = null;
        if ($message->direction === 'from_stable' && $message->sender_user_id !== null) {
            $user = User::query()->find($message->sender_user_id);
            $senderName = $user?->name;
        } elseif ($message->direction === 'from_client') {
            $senderName = $message->client?->name;
        }

        $attachments = is_array($message->attachments) ? $message->attachments : [];

        return new HorseMessageSnapshot(
            id: (string) $message->id,
            stableTenantId: (string) $stableTenant->id,
            direction: (string) $message->direction,
            subject: $message->subject !== null ? (string) $message->subject : null,
            body: (string) $message->body,
            senderName: $senderName,
            sentAt: $message->sent_at instanceof Carbon ? $message->sent_at : Carbon::parse((string) ($message->sent_at ?? $message->created_at)),
            readByClientAt: $message->read_by_client_at instanceof Carbon ? $message->read_by_client_at : null,
            readByStableAt: $message->read_by_stable_at instanceof Carbon ? $message->read_by_stable_at : null,
            attachmentCount: count($attachments),
            attachments: $attachments,
        );
    }
}
