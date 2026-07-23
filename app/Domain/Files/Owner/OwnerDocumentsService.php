<?php

declare(strict_types=1);

namespace App\Domain\Files\Owner;

use App\Domain\Files\Owner\Snapshots\HorseDocumentSnapshot;
use App\Enums\HorseDocumentKind;
use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Tenant\Client;
use App\Models\Tenant\Horse;
use App\Models\Tenant\HorseDocument;
use App\Tenancy\TenantManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cross-tenant service dla dokumentów konia. Analogiczny do
 * OwnerPhotosService ale z dodatkowymi polami: kind (enum z
 * HorseDocumentKind), valid_from/valid_until, większy size limit
 * i więcej MIME types (Office + PDF + images).
 *
 * Storage path: `horse-documents/{stable_tenant_id}/{horse_id}/
 *   {uploader}-{ulid}_{sanitized_name}`
 *
 * Limity:
 *   - Max 25 MB / plik (PDF skany kontraktów mogą być duże)
 *   - MIME: PDF + Office (Word/Excel) + images (JPG/PNG/WebP)
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 5".
 */
class OwnerDocumentsService
{
    public const MAX_SIZE_BYTES = 25 * 1024 * 1024; // 25 MB

    public const ALLOWED_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function __construct(
        private readonly TenantManager $tenants,
    ) {}

    /**
     * @return list<HorseDocumentSnapshot>
     *
     * @throws AuthorizationException
     */
    public function listForHorse(User $owner, string $centralHorseId): array
    {
        $this->ensureOwnership($owner, $centralHorseId);
        $stableTenant = $this->resolveStableTenant($owner, $centralHorseId);

        return $this->tenants->execute($stableTenant, function () use ($centralHorseId, $stableTenant): array {
            $horse = Horse::query()->where('central_horse_id', $centralHorseId)->first();
            if ($horse === null) {
                return [];
            }

            $docs = HorseDocument::query()
                ->where('horse_id', $horse->id)
                ->orderBy('kind')
                ->orderByDesc('created_at')
                ->get();

            return array_map(fn (HorseDocument $d) => $this->mapToSnapshot($d, $stableTenant), $docs->all());
        });
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function upload(
        User $owner,
        string $centralHorseId,
        UploadedFile $file,
        string $kind,
        string $name,
        ?string $description = null,
        ?Carbon $validFrom = null,
        ?Carbon $validUntil = null,
    ): HorseDocumentSnapshot {
        $this->ensureOwnership($owner, $centralHorseId);
        $assignment = $this->requireActiveAssignment($owner, $centralHorseId);
        $this->validateKind($kind);
        $this->validateFile($file);

        $stableTenant = Tenant::query()->find($assignment->stable_tenant_id);
        if ($stableTenant === null) {
            throw new RuntimeException("Stable tenant {$assignment->stable_tenant_id} not found");
        }

        return $this->tenants->execute($stableTenant, function () use ($owner, $centralHorseId, $file, $kind, $name, $description, $validFrom, $validUntil, $stableTenant): HorseDocumentSnapshot {
            $horse = Horse::query()->where('central_horse_id', $centralHorseId)->first();
            if ($horse === null) {
                throw new RuntimeException('Horse not found in stable DB');
            }
            $client = Client::query()->where('central_user_id', $owner->id)->first();

            $path = $this->storeFile($file, $stableTenant->id, (string) $horse->id);

            $doc = HorseDocument::create([
                'id' => (string) Str::ulid(),
                'horse_id' => $horse->id,
                'name' => $name,
                'kind' => $kind,
                'description' => $description,
                'file_path' => $path,
                'original_name' => (string) $file->getClientOriginalName(),
                'mime' => (string) $file->getMimeType(),
                'size_bytes' => (int) $file->getSize(),
                'uploaded_by_role' => 'client',
                'uploaded_by_user_id' => null,
                'uploaded_by_client_id' => $client?->id,
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
            ]);

            return $this->mapToSnapshot($doc, $stableTenant);
        });
    }

    /**
     * Owner soft-deletes swój dokument (uploaded_by_role='client').
     * Stable uploads chronione.
     *
     * @throws AuthorizationException
     */
    public function delete(User $owner, string $stableTenantId, string $documentId): void
    {
        $stableTenant = Tenant::query()->find($stableTenantId);
        if ($stableTenant === null) {
            return;
        }

        $this->tenants->execute($stableTenant, function () use ($owner, $documentId): void {
            $doc = HorseDocument::query()->find($documentId);
            if ($doc === null) {
                return;
            }
            if ($doc->uploaded_by_role !== 'client') {
                throw new AuthorizationException(__('owner/documents.access.cannot_delete_stable'));
            }
            $client = Client::query()->where('central_user_id', $owner->id)->first();
            if ($client === null || $doc->uploaded_by_client_id !== $client->id) {
                throw new AuthorizationException(__('owner/documents.access.cannot_delete_other'));
            }
            $doc->delete(); // soft delete
        });
    }

    /**
     * @return array{path: string, mime: string, original_name: string, stable_tenant: Tenant}|null
     */
    public function findForDownload(User $owner, string $stableTenantId, string $documentId): ?array
    {
        $stableTenant = Tenant::query()->find($stableTenantId);
        if ($stableTenant === null) {
            return null;
        }

        return $this->tenants->execute($stableTenant, function () use ($owner, $documentId, $stableTenant): ?array {
            $doc = HorseDocument::query()->find($documentId);
            if ($doc === null) {
                return null;
            }
            $horse = Horse::query()->find($doc->horse_id);
            if ($horse === null || $horse->central_horse_id === null) {
                return null;
            }
            $isOwner = CentralHorseRegistry::query()
                ->where('id', $horse->central_horse_id)
                ->where('primary_owner_user_id', $owner->id)
                ->exists();
            if (! $isOwner) {
                return null;
            }

            return [
                'path' => (string) $doc->file_path,
                'mime' => (string) $doc->mime,
                'original_name' => (string) $doc->original_name,
                'stable_tenant' => $stableTenant,
            ];
        });
    }

    public function streamFile(Tenant $stableTenant, string $filePath, string $mime, string $originalName): StreamedResponse
    {
        if (! $this->pathBelongsToStable($filePath, $stableTenant)) {
            throw new AuthorizationException(__('owner/documents.access.path_mismatch'));
        }
        if (! Storage::disk($this->disk())->exists($filePath)) {
            throw new RuntimeException('Document file not found on disk');
        }

        return Storage::disk($this->disk())->response($filePath, $originalName, [
            'Content-Type' => $mime,
        ]);
    }

    private function storeFile(UploadedFile $file, string $stableTenantId, string $horseId): string
    {
        $dir = "horse-documents/{$stableTenantId}/{$horseId}";
        $sanitized = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
        $filename = 'client-'.(string) Str::ulid().'_'.$sanitized;
        $path = $file->storeAs($dir, $filename, $this->disk());
        if ($path === false || $path === '') {
            throw new RuntimeException('Failed to store document file');
        }

        return (string) $path;
    }

    private function disk(): string
    {
        return (string) config('hovera.uploads.disk', 'local');
    }

    private function validateFile(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw ValidationException::withMessages([
                'file' => __('owner/documents.error.too_large', [
                    'name' => $file->getClientOriginalName(),
                    'max_mb' => self::MAX_SIZE_BYTES / 1024 / 1024,
                ]),
            ]);
        }
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages([
                'file' => __('owner/documents.error.unsupported_mime', [
                    'mime' => $mime,
                    'name' => $file->getClientOriginalName(),
                ]),
            ]);
        }
    }

    private function validateKind(string $kind): void
    {
        if (HorseDocumentKind::tryFrom($kind) === null) {
            throw ValidationException::withMessages([
                'kind' => __('owner/documents.error.invalid_kind', ['kind' => $kind]),
            ]);
        }
    }

    private function ensureOwnership(User $owner, string $centralHorseId): void
    {
        $exists = CentralHorseRegistry::query()
            ->where('id', $centralHorseId)
            ->where('primary_owner_user_id', $owner->id)
            ->exists();
        if (! $exists) {
            throw new AuthorizationException(__('owner/documents.access.not_owner'));
        }
    }

    private function requireActiveAssignment(User $owner, string $centralHorseId): HorseBoardingAssignment
    {
        $a = HorseBoardingAssignment::query()
            ->where('central_horse_id', $centralHorseId)
            ->where('owner_user_id', $owner->id)
            ->where('status', HorseBoardingAssignment::STATUS_ACTIVE)
            ->first();
        if ($a === null) {
            throw new AuthorizationException(__('owner/documents.access.upload_requires_active_boarding'));
        }

        return $a;
    }

    private function resolveStableTenant(User $owner, string $centralHorseId): Tenant
    {
        $a = HorseBoardingAssignment::query()
            ->where('central_horse_id', $centralHorseId)
            ->where('owner_user_id', $owner->id)
            ->whereIn('status', [
                HorseBoardingAssignment::STATUS_ACTIVE,
                HorseBoardingAssignment::STATUS_ENDED,
            ])
            ->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")
            ->latest('started_at')
            ->first();
        if ($a === null) {
            throw new RuntimeException("No boarding assignment for {$centralHorseId}");
        }
        $tenant = Tenant::query()->find($a->stable_tenant_id);
        if ($tenant === null) {
            throw new RuntimeException("Stable tenant {$a->stable_tenant_id} not found");
        }

        return $tenant;
    }

    private function pathBelongsToStable(string $path, Tenant $stable): bool
    {
        return str_starts_with($path, "horse-documents/{$stable->id}/");
    }

    private function mapToSnapshot(HorseDocument $doc, Tenant $stableTenant): HorseDocumentSnapshot
    {
        $uploaderName = null;
        if ($doc->uploaded_by_role === 'stable' && $doc->uploaded_by_user_id !== null) {
            $uploaderName = User::query()->find($doc->uploaded_by_user_id)?->name;
        } elseif ($doc->uploaded_by_role === 'client' && $doc->uploaded_by_client_id !== null) {
            $uploaderName = Client::query()->find($doc->uploaded_by_client_id)?->name;
        }

        return new HorseDocumentSnapshot(
            id: (string) $doc->id,
            stableTenantId: (string) $stableTenant->id,
            name: (string) $doc->name,
            kind: $doc->kind->value,
            description: $doc->description !== null ? (string) $doc->description : null,
            originalName: (string) $doc->original_name,
            mime: (string) $doc->mime,
            sizeBytes: (int) $doc->size_bytes,
            validFrom: $doc->valid_from instanceof Carbon ? $doc->valid_from : null,
            validUntil: $doc->valid_until instanceof Carbon ? $doc->valid_until : null,
            uploadedByRole: (string) $doc->uploaded_by_role,
            uploaderName: $uploaderName,
            createdAt: $doc->created_at instanceof Carbon ? $doc->created_at : Carbon::parse((string) $doc->created_at),
        );
    }
}
