<?php

declare(strict_types=1);

namespace App\Domain\Files\Owner;

use App\Domain\Files\Owner\Snapshots\HorsePhotoSnapshot;
use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Tenant\Client;
use App\Models\Tenant\Horse;
use App\Models\Tenant\HorsePhoto;
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
 * Cross-tenant service dla zdjęć konia — owner panel widzi galerię
 * (stable + own uploads), może dodawać własne, kasować swoje. Stable
 * widzi obie strony z badge "uploaded_by_role" w istniejącym Filament
 * HorseResource (badge dorobimy w PR 5.4).
 *
 * Storage path: `horse-photos/{stable_tenant_id}/{horse_id}/
 *   {uploader}-{ulid}_{sanitized_name}`
 *
 * Limity:
 *   - Max 10 MB / plik (zdjęcia nie powinny być duże; full HD JPEG ~3 MB)
 *   - MIME: image/jpeg, image/png, image/webp tylko
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 5".
 */
class OwnerPhotosService
{
    public const MAX_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB

    public const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function __construct(
        private readonly TenantManager $tenants,
    ) {}

    /**
     * Lista zdjęć dla konia (oba kierunki — stable + owner). Sortowanie
     * DESC po created_at. Gate: primary_owner + active/ended boarding.
     *
     * @return list<HorsePhotoSnapshot>
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

            $photos = HorsePhoto::query()
                ->where('horse_id', $horse->id)
                ->orderByDesc('created_at')
                ->get();

            return array_map(fn (HorsePhoto $p) => $this->mapToSnapshot($p, $stableTenant), $photos->all());
        });
    }

    /**
     * Owner dodaje własne zdjęcie. Wymaga ACTIVE boarding'u (ended =
     * read-only per Q3 roadmap).
     *
     * @throws AuthorizationException
     * @throws ValidationException przy invalid file
     */
    public function upload(User $owner, string $centralHorseId, UploadedFile $file, ?string $caption = null): HorsePhotoSnapshot
    {
        $this->ensureOwnership($owner, $centralHorseId);
        $assignment = $this->requireActiveAssignment($owner, $centralHorseId);
        $this->validateFile($file);

        $stableTenant = Tenant::query()->find($assignment->stable_tenant_id);
        if ($stableTenant === null) {
            throw new RuntimeException("Stable tenant {$assignment->stable_tenant_id} not found");
        }

        return $this->tenants->execute($stableTenant, function () use ($owner, $centralHorseId, $file, $caption, $stableTenant): HorsePhotoSnapshot {
            $horse = Horse::query()->where('central_horse_id', $centralHorseId)->first();
            if ($horse === null) {
                throw new RuntimeException('Horse not found in stable DB');
            }
            $client = Client::query()->where('central_user_id', $owner->id)->first();

            $path = $this->storeFile($file, $stableTenant->id, (string) $horse->id);

            $photo = HorsePhoto::create([
                'id' => (string) Str::ulid(),
                'horse_id' => $horse->id,
                'file_path' => $path,
                'original_name' => (string) $file->getClientOriginalName(),
                'mime' => (string) $file->getMimeType(),
                'size_bytes' => (int) $file->getSize(),
                'caption' => $caption,
                'sort_order' => 0,
                'uploaded_by_role' => 'client',
                'uploaded_by_user_id' => null,
                'uploaded_by_client_id' => $client?->id,
            ]);

            return $this->mapToSnapshot($photo, $stableTenant);
        });
    }

    /**
     * Owner usuwa SWOJE zdjęcie (uploaded_by_role='client'). Soft delete
     * (HorsePhoto ma SoftDeletes). Plik z dysku zostaje (defensive — może
     * inny system referuje; cleanup w przyszłej iteracji jako job).
     *
     * @throws AuthorizationException gdy nie owner / nie jego upload
     */
    public function delete(User $owner, string $stableTenantId, string $photoId): void
    {
        $stableTenant = Tenant::query()->find($stableTenantId);
        if ($stableTenant === null) {
            return;
        }

        $this->tenants->execute($stableTenant, function () use ($owner, $photoId): void {
            $photo = HorsePhoto::query()->find($photoId);
            if ($photo === null) {
                return; // silent — stale id
            }

            // Tylko swoje uploady (uploaded_by_role='client' + matching
            // client.central_user_id). Stable uploads kasuje operator
            // stajni w swoim panel'u.
            if ($photo->uploaded_by_role !== 'client') {
                throw new AuthorizationException(__('owner/photos.access.cannot_delete_stable'));
            }
            $client = Client::query()->where('central_user_id', $owner->id)->first();
            if ($client === null || $photo->uploaded_by_client_id !== $client->id) {
                throw new AuthorizationException(__('owner/photos.access.cannot_delete_other'));
            }

            $photo->delete(); // soft delete
        });
    }

    /**
     * Stream pliku — używany przez download endpoint. Caller MUSI
     * wcześniej zweryfikować ownership.
     */
    public function streamFile(Tenant $stableTenant, string $filePath, string $mime, string $originalName): StreamedResponse
    {
        if (! $this->pathBelongsToStable($filePath, $stableTenant)) {
            throw new AuthorizationException(__('owner/photos.access.path_mismatch'));
        }
        if (! Storage::disk($this->disk())->exists($filePath)) {
            throw new RuntimeException('Photo file not found on disk');
        }

        return Storage::disk($this->disk())->response($filePath, $originalName, [
            'Content-Type' => $mime,
        ]);
    }

    /**
     * Helper: znajdź photo + zweryfikuj ownership. Zwraca tuple z path/
     * mime/name żeby controller mógł stream'ować po wyjściu z execute().
     *
     * @return array{path: string, mime: string, original_name: string, stable_tenant: Tenant}|null
     */
    public function findForDownload(User $owner, string $stableTenantId, string $photoId): ?array
    {
        $stableTenant = Tenant::query()->find($stableTenantId);
        if ($stableTenant === null) {
            return null;
        }

        return $this->tenants->execute($stableTenant, function () use ($owner, $photoId, $stableTenant): ?array {
            $photo = HorsePhoto::query()->find($photoId);
            if ($photo === null) {
                return null;
            }

            // Verify że to jest koń ownera — load horse + central registry
            // matching primary_owner.
            $horse = Horse::query()->find($photo->horse_id);
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
                'path' => (string) $photo->file_path,
                'mime' => (string) $photo->mime,
                'original_name' => (string) $photo->original_name,
                'stable_tenant' => $stableTenant,
            ];
        });
    }

    private function storeFile(UploadedFile $file, string $stableTenantId, string $horseId): string
    {
        $dir = "horse-photos/{$stableTenantId}/{$horseId}";
        $sanitized = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
        $filename = 'client-'.(string) Str::ulid().'_'.$sanitized;

        $path = $file->storeAs($dir, $filename, $this->disk());
        if ($path === false || $path === '') {
            throw new RuntimeException('Failed to store photo file');
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
                'file' => __('owner/photos.error.too_large', [
                    'name' => $file->getClientOriginalName(),
                    'max_mb' => self::MAX_SIZE_BYTES / 1024 / 1024,
                ]),
            ]);
        }
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages([
                'file' => __('owner/photos.error.unsupported_mime', [
                    'mime' => $mime,
                    'name' => $file->getClientOriginalName(),
                ]),
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
            throw new AuthorizationException(__('owner/photos.access.not_owner'));
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
            throw new AuthorizationException(__('owner/photos.access.upload_requires_active_boarding'));
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
        return str_starts_with($path, "horse-photos/{$stable->id}/");
    }

    private function mapToSnapshot(HorsePhoto $photo, Tenant $stableTenant): HorsePhotoSnapshot
    {
        // Uploader name z central User (stable) lub Client (owner).
        $uploaderName = null;
        if ($photo->uploaded_by_role === 'stable' && $photo->uploaded_by_user_id !== null) {
            $uploaderName = User::query()->find($photo->uploaded_by_user_id)?->name;
        } elseif ($photo->uploaded_by_role === 'client' && $photo->uploaded_by_client_id !== null) {
            $uploaderName = Client::query()->find($photo->uploaded_by_client_id)?->name;
        }

        return new HorsePhotoSnapshot(
            id: (string) $photo->id,
            stableTenantId: (string) $stableTenant->id,
            originalName: (string) $photo->original_name,
            caption: $photo->caption !== null ? (string) $photo->caption : null,
            mime: (string) $photo->mime,
            sizeBytes: (int) $photo->size_bytes,
            sortOrder: (int) $photo->sort_order,
            uploadedByRole: (string) $photo->uploaded_by_role,
            uploaderName: $uploaderName,
            createdAt: $photo->created_at instanceof Carbon ? $photo->created_at : Carbon::parse((string) $photo->created_at),
        );
    }
}
