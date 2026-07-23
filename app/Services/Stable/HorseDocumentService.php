<?php

declare(strict_types=1);

namespace App\Services\Stable;

use App\Enums\HorseDocumentKind;
use App\Models\Central\Tenant;
use App\Models\Tenant\Horse;
use App\Models\Tenant\HorseDocument;
use App\Services\TenantAuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Upload + delete dokumentów konia. Same MIME whitelist + size limit
 * co attachments wiadomości, ale 25 MB / plik (paszporty bywają duże).
 */
class HorseDocumentService
{
    public const MAX_SIZE_BYTES = 25 * 1024 * 1024;   // 25 MB

    public const ALLOWED_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg', 'image/png', 'image/webp', 'image/heic',
        'text/plain',
    ];

    public function __construct(
        private readonly TenantAuditLogger $audit,
    ) {}

    public function uploadByStable(
        Tenant $tenant,
        Horse $horse,
        UploadedFile $file,
        string $name,
        HorseDocumentKind $kind,
        ?string $description = null,
        ?string $validFrom = null,
        ?string $validUntil = null,
        ?string $uploadedByUserId = null,
    ): HorseDocument {
        return $this->store($tenant, $horse, $file, [
            'name' => $name,
            'kind' => $kind->value,
            'description' => $description,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            'uploaded_by_role' => 'stable',
            'uploaded_by_user_id' => $uploadedByUserId,
        ]);
    }

    public function uploadByClient(
        Tenant $tenant,
        Horse $horse,
        string $clientId,
        UploadedFile $file,
        string $name,
        HorseDocumentKind $kind,
        ?string $description = null,
    ): HorseDocument {
        if ($horse->owner_client_id !== $clientId) {
            throw ValidationException::withMessages([
                'horse' => 'Brak uprawnień — ten koń nie należy do Ciebie.',
            ]);
        }

        return $this->store($tenant, $horse, $file, [
            'name' => $name,
            'kind' => $kind->value,
            'description' => $description,
            'uploaded_by_role' => 'client',
            'uploaded_by_client_id' => $clientId,
        ]);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function store(Tenant $tenant, Horse $horse, UploadedFile $file, array $payload): HorseDocument
    {
        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw ValidationException::withMessages([
                'file' => 'Dokument przekracza 25 MB.',
            ]);
        }
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages([
                'file' => 'Niewspierany typ pliku: '.$mime,
            ]);
        }

        $dir = "horse-documents/{$tenant->id}/{$horse->id}";
        $diskName = (string) Str::ulid().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
        $path = $file->storeAs($dir, $diskName, $this->disk());

        $doc = HorseDocument::create([
            'id' => (string) Str::ulid(),
            'horse_id' => $horse->id,
            'name' => $payload['name'],
            'kind' => $payload['kind'],
            'description' => $payload['description'] ?? null,
            'file_path' => (string) $path,
            'original_name' => (string) $file->getClientOriginalName(),
            'mime' => $mime,
            'size_bytes' => (int) $file->getSize(),
            'uploaded_by_role' => $payload['uploaded_by_role'],
            'uploaded_by_user_id' => $payload['uploaded_by_user_id'] ?? null,
            'uploaded_by_client_id' => $payload['uploaded_by_client_id'] ?? null,
            'valid_from' => $payload['valid_from'] ?? null,
            'valid_until' => $payload['valid_until'] ?? null,
        ]);

        $this->audit->record('horse_document.uploaded', 'HorseDocument', (string) $doc->id, [
            'horse_id' => $horse->id,
            'kind' => $payload['kind'],
            'role' => $payload['uploaded_by_role'],
        ]);

        return $doc;
    }

    /**
     * Usuwa dokument + plik z dysku. Soft-delete by default; jeśli
     * deleter jest klientem, weryfikuje że to JEGO upload (nie może
     * usunąć tego co stajnia wgrała).
     */
    public function delete(HorseDocument $doc, ?string $byClientId = null): void
    {
        if ($byClientId !== null && $doc->uploaded_by_client_id !== $byClientId) {
            throw ValidationException::withMessages([
                'document' => 'Możesz usunąć tylko własne dokumenty.',
            ]);
        }

        // Soft delete + nie kasujemy fizycznego pliku — gdy ktoś przywróci
        // z trasha plik wraca też. Trash purge w przyszłości może czyścić.
        $doc->delete();

        $this->audit->record('horse_document.deleted', 'HorseDocument', (string) $doc->id);
    }

    private function disk(): string
    {
        return (string) config('hovera.uploads.disk', 'local');
    }
}
