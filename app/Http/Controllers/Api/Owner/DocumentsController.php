<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Owner;

use App\Domain\Files\Owner\OwnerDocumentsService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Owner panel API dla dokumentów konia (paszport, kontrakt, ubezpieczenie,
 * książeczka szczepień etc.). Read (oba kierunki), write owner-only,
 * delete swoich.
 *
 * Endpointy:
 *   GET    /api/owner/horses/{centralHorseId}/documents
 *   POST   /api/owner/horses/{centralHorseId}/documents
 *   GET    /api/owner/documents/{stableTenantId}/{documentId}/download
 *   DELETE /api/owner/documents/{stableTenantId}/{documentId}
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 5".
 */
class DocumentsController extends Controller
{
    public function __construct(
        private readonly OwnerDocumentsService $service,
    ) {}

    public function index(Request $request, string $centralHorseId): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        try {
            $documents = $this->service->listForHorse($user, $centralHorseId);
        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json([
            'data' => array_map(fn ($d) => $d->toArray(), $documents),
            'count' => count($documents),
        ]);
    }

    public function upload(Request $request, string $centralHorseId): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $validated = $request->validate([
            'file' => 'required|file',
            'name' => 'required|string|max:200',
            'kind' => 'required|string|max:32',
            'description' => 'nullable|string|max:1000',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
        ]);

        try {
            $snapshot = $this->service->upload(
                $user,
                $centralHorseId,
                $request->file('file'),
                $validated['kind'],
                $validated['name'],
                $validated['description'] ?? null,
                isset($validated['valid_from']) ? Carbon::parse($validated['valid_from']) : null,
                isset($validated['valid_until']) ? Carbon::parse($validated['valid_until']) : null,
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

    public function download(Request $request, string $stableTenantId, string $documentId): StreamedResponse|JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $resolved = $this->service->findForDownload($user, $stableTenantId, $documentId);
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

    public function destroy(Request $request, string $stableTenantId, string $documentId): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        try {
            $this->service->delete($user, $stableTenantId, $documentId);
        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }

        return response()->json(['ok' => true]);
    }
}
