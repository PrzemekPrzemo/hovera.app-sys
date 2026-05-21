<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Owner;

use App\Domain\Messages\Owner\OwnerMessagesService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RuntimeException;
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
}
