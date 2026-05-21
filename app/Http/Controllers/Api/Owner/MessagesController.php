<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Owner;

use App\Domain\Messages\Owner\HorseMessageAttachmentStorage;
use App\Domain\Messages\Owner\OwnerMessagesService;
use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Tenant\Client;
use App\Models\Tenant\Horse;
use App\Models\Tenant\HorseMessage;
use App\Tenancy\TenantManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Cross-tenant API dla wiadomości Owner ↔ Stable. Owner czyta i pisze
 * do stajni gdzie boarduje jego koń. Stable side używa istniejącego
 * Filament UI w stable panel'u (HorseResource Messages tab — Faza 4.4
 * doszyje notification).
 *
 * Wszystkie endpointy wymagają auth:sanctum (SPA session) + ownership
 * weryfikacja w serwisie.
 *
 * Endpointy:
 *   GET  /api/owner/horses/{centralHorseId}/messages
 *   POST /api/owner/horses/{centralHorseId}/messages          {subject?, body}
 *   POST /api/owner/messages/{stableTenantId}/{messageId}/read
 *   GET  /api/owner/messages/unread-count
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 4 PR 4.1".
 */
class MessagesController extends Controller
{
    public function __construct(
        private readonly OwnerMessagesService $service,
        private readonly HorseMessageAttachmentStorage $attachments,
        private readonly TenantManager $tenants,
    ) {}

    public function indexForHorse(Request $request, string $centralHorseId): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        try {
            $messages = $this->service->listForHorse($user, $centralHorseId);
        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json([
            'data' => array_map(fn ($m) => $m->toArray(), $messages),
            'count' => count($messages),
        ]);
    }

    public function send(Request $request, string $centralHorseId): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $validated = $request->validate([
            'subject' => 'nullable|string|max:200',
            'body' => 'required|string|max:10000',
            'attachments' => 'nullable|array|max:10',
            'attachments.*.path' => 'required_with:attachments|string|max:500',
            'attachments.*.original_name' => 'nullable|string|max:255',
            'attachments.*.mime' => 'nullable|string|max:100',
            'attachments.*.size' => 'nullable|integer|min:0|max:26214400', // 25MB
        ]);

        try {
            $message = $this->service->send(
                $user,
                $centralHorseId,
                $validated['subject'] ?? null,
                $validated['body'],
                $validated['attachments'] ?? [],
            );
        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (RuntimeException $e) {
            // Owner ownership OK ale stable state broken (np. brak Client).
            // Logujemy + 422 z czytelnym message'em.
            report($e);

            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'Failed to send message'], 500);
        }

        return response()->json(['data' => $message->toArray()], 201);
    }

    public function markRead(Request $request, string $stableTenantId, string $messageId): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $this->service->markRead($user, $stableTenantId, $messageId);

        return response()->json(['ok' => true]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        return response()->json([
            'count' => $this->service->unreadCount($user),
        ]);
    }

    /**
     * Upload pojedynczego pliku — używamy oddzielnego endpoint'u zamiast
     * multipartu w `send` żeby UI mógł pokazywać progress per plik i
     * obsługiwać drag-drop bez full-page reload.
     *
     * Zwraca metadata którą caller embeduje w `attachments[]` w
     * subsequent POST /messages.
     */
    public function uploadAttachment(Request $request, string $centralHorseId): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Gate: ownership + active boarding (upload pliku tylko gdy może
        // wysłać wiadomość; ended = read-only więc upload też zabroniony).
        if (! $this->ownerOwnsHorse($user, $centralHorseId)) {
            return response()->json(['error' => __('owner/messages.access.not_owner')], 403);
        }

        $assignment = HorseBoardingAssignment::query()
            ->where('central_horse_id', $centralHorseId)
            ->where('owner_user_id', $user->id)
            ->where('status', HorseBoardingAssignment::STATUS_ACTIVE)
            ->first();

        if ($assignment === null) {
            return response()->json([
                'error' => __('owner/messages.access.send_requires_active_boarding'),
            ], 403);
        }

        $request->validate(['file' => 'required|file']);

        $stableTenant = Tenant::query()->find($assignment->stable_tenant_id);
        if ($stableTenant === null) {
            return response()->json(['error' => 'Stable tenant not found'], 404);
        }

        // Cross-tenant context — resolve horse w stable DB żeby dostać
        // jego ULID (horse_id w storage path). Bez execute() Horse::query
        // poszłoby na owner tenant DB.
        try {
            $metadata = $this->tenants->execute($stableTenant, function () use ($stableTenant, $centralHorseId, $request): array {
                $horse = Horse::query()->where('central_horse_id', $centralHorseId)->first();
                if ($horse === null) {
                    throw new RuntimeException('Horse not found in stable DB');
                }

                return $this->attachments->storeUploadedFile(
                    $stableTenant,
                    (string) $horse->id,
                    $request->file('file'),
                    HorseMessageAttachmentStorage::UPLOADER_OWNER,
                );
            });
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'Upload failed'], 500);
        }

        return response()->json(['data' => $metadata], 201);
    }

    /**
     * Stream pliku attachment. `attachmentIndex` to pozycja w `attachments`
     * JSON na HorseMessage (0-indexed).
     */
    public function downloadAttachment(
        Request $request,
        string $stableTenantId,
        string $messageId,
        int $attachmentIndex,
    ): StreamedResponse|JsonResponse {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $stableTenant = Tenant::query()->find($stableTenantId);
        if ($stableTenant === null) {
            return response()->json(['error' => 'Not found'], 404);
        }

        // Cross-tenant resolve message + ownership check (Client.central_user_id
        // matching). Po execute() trzymamy tylko ścieżkę pliku (path) — Storage
        // robimy poza execute żeby stream był independent od tenant connection.
        $attachment = $this->tenants->execute($stableTenant, function () use ($user, $messageId, $attachmentIndex): ?array {
            $client = Client::query()->where('central_user_id', $user->id)->first();
            if ($client === null) {
                return null;
            }

            $message = HorseMessage::query()
                ->where('id', $messageId)
                ->where('client_id', $client->id)
                ->first();

            if ($message === null) {
                return null;
            }

            $list = is_array($message->attachments) ? $message->attachments : [];

            return $list[$attachmentIndex] ?? null;
        });

        if ($attachment === null || ! is_array($attachment)) {
            return response()->json(['error' => 'Not found'], 404);
        }

        // Defensive: path MUSI należeć do tego stable (zabezpieczenie przed
        // podstawioną ścieżką typu '../../inny-tenant/...').
        if (! $this->attachments->pathBelongsToStable((string) ($attachment['path'] ?? ''), $stableTenant)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        try {
            return $this->attachments->streamFromAttachment($attachment);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    private function ownerOwnsHorse(User $owner, string $centralHorseId): bool
    {
        return CentralHorseRegistry::query()
            ->where('id', $centralHorseId)
            ->where('primary_owner_user_id', $owner->id)
            ->exists();
    }
}
