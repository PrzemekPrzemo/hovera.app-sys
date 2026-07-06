<?php

declare(strict_types=1);

namespace App\Domain\Messages\Owner;

use App\Domain\Messages\Owner\Snapshots\HorseMessageSnapshot;
use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Models\Tenant\Client;
use App\Models\Tenant\Horse;
use App\Models\Tenant\HorseMessage;
use App\Notifications\OwnerSentMessageToStableNotification;
use App\Tenancy\TenantManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification as NotificationFacade;
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
                ->with('client')
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

        // Wewnątrz execute: tworzymy message + zbieramy horse name dla
        // notification payload. snapshot + horse name zwracamy razem.
        $result = $this->tenants->execute($stableTenant, function () use ($owner, $centralHorseId, $subject, $body, $attachments, $stableTenant): array {
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

            return [
                'snapshot' => $this->mapToSnapshot($message, $stableTenant),
                'horse_id' => (string) $horse->id,
                'horse_name' => (string) $horse->name,
            ];
        });

        // Po execute connection wraca na central (lub null) — dispatch
        // notifications (database + mail) do team members stajni.
        // Database notifications zapisują do `notifications` table w
        // central DB, dlatego MUSI być poza execute. Patrz Faza 4 PR 4.4.
        $this->notifyStableTeam(
            $stableTenant,
            $owner,
            $result['horse_id'],
            $result['horse_name'],
            $subject,
            $body,
            $attachments,
        );

        return $result['snapshot'];
    }

    /**
     * Dispatchuje OwnerSentMessageToStableNotification do wszystkich
     * tenant team members z rolami operator/owner/admin/manager. Idzie
     * po central DB (TenantMembership + User), więc MUSI być wywołane
     * poza TenantManager::execute (gdy connection już wrócił).
     *
     * Soft fail — gdy notification dispatch padnie (np. mail config error),
     * logujemy ale send() już zakończony, message zapisany. Nie chcemy
     * cofać tylko dlatego że SMTP padło.
     *
     * @param  array<int, array<string,mixed>>  $attachments
     */
    private function notifyStableTeam(
        Tenant $stable,
        User $owner,
        string $stableHorseId,
        string $horseName,
        ?string $subject,
        string $body,
        array $attachments,
    ): void {
        try {
            $teamUserIds = TenantMembership::query()
                ->where('tenant_id', $stable->id)
                ->whereIn('role', ['owner', 'admin', 'operator', 'manager'])
                ->whereNull('revoked_at')
                ->pluck('user_id')
                ->all();

            if ($teamUserIds === []) {
                return;
            }

            $teamUsers = User::query()->whereIn('id', $teamUserIds)->get();
            if ($teamUsers->isEmpty()) {
                return;
            }

            $notification = new OwnerSentMessageToStableNotification(
                ownerName: (string) ($owner->name ?? $owner->email),
                horseName: $horseName,
                stableHorseId: $stableHorseId,
                subject: $subject,
                bodyPreview: $this->truncate($body, 280),
                attachmentCount: count($attachments),
                stableHorseUrl: $this->stableHorseUrl($stable, $stableHorseId),
            );

            NotificationFacade::send($teamUsers, $notification);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function truncate(string $text, int $limit): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        return mb_strlen($text) <= $limit ? $text : mb_substr($text, 0, $limit - 1).'…';
    }

    private function stableHorseUrl(Tenant $stable, string $stableHorseId): string
    {
        // /app/horses/{id} — stable panel route. Slug stable panel'u to
        // domyślnie 'app' (patrz AppPanelProvider).
        return url(sprintf('/app/horses/%s', $stableHorseId));
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
