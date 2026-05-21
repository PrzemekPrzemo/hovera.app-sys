<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Owner;

use App\Domain\Files\Owner\OwnerPhotosService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Owner panel API dla zdjęć konia. Read (oba kierunki — stable + owner
 * uploads), write tylko owner's (uploaded_by_role='client'), delete tylko
 * swoich.
 *
 * Endpointy:
 *   GET    /api/owner/horses/{centralHorseId}/photos                      list
 *   POST   /api/owner/horses/{centralHorseId}/photos                      upload (file + caption)
 *   GET    /api/owner/photos/{stableTenantId}/{photoId}/download           stream
 *   DELETE /api/owner/photos/{stableTenantId}/{photoId}                    soft delete
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 5".
 */
class PhotosController extends Controller
{
    public function __construct(
        private readonly OwnerPhotosService $service,
    ) {}

    public function index(Request $request, string $centralHorseId): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        try {
            $photos = $this->service->listForHorse($user, $centralHorseId);
        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json([
            'data' => array_map(fn ($p) => $p->toArray(), $photos),
            'count' => count($photos),
        ]);
    }

    public function upload(Request $request, string $centralHorseId): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'file' => 'required|file',
            'caption' => 'nullable|string|max:500',
        ]);

        try {
            $snapshot = $this->service->upload(
                $user,
                $centralHorseId,
                $request->file('file'),
                $request->input('caption'),
            );
        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'Upload failed'], 500);
        }

        return response()->json(['data' => $snapshot->toArray()], 201);
    }

    public function download(Request $request, string $stableTenantId, string $photoId): StreamedResponse|JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $resolved = $this->service->findForDownload($user, $stableTenantId, $photoId);
        if ($resolved === null) {
            return response()->json(['error' => 'Not found'], 404);
        }

        try {
            return $this->service->streamFile(
                $resolved['stable_tenant'],
                $resolved['path'],
                $resolved['mime'],
                $resolved['original_name'],
            );
        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    public function destroy(Request $request, string $stableTenantId, string $photoId): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        try {
            $this->service->delete($user, $stableTenantId, $photoId);
        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }

        return response()->json(['ok' => true]);
    }
}
