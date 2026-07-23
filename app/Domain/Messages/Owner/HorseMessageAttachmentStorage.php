<?php

declare(strict_types=1);

namespace App\Domain\Messages\Owner;

use App\Models\Central\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Storage layer dla attachments w HorseMessage — owner ↔ stable. Pliki
 * trafiają na 'local' disk z per-stable prefiksem; download streamowany
 * przez authenticated controller (nie public URL).
 *
 * Convention path:
 *   horse-messages/{stable_tenant_id}/{horse_id}/{uploader}-{ulid}_{sanitized_name}
 *
 *   * `uploader` = 'owner' | 'stable' — w nazwie żeby przy debug'u od
 *     razu widać kto wgrał
 *
 * Limity (per docs/OWNER-STABLE-ROADMAP.md Faza 4 PR 4.2):
 *   - Max 10 plików / wiadomość (walidacja w controller'ze)
 *   - Max 25 MB / plik
 *   - Allowed MIME: image/jpeg, image/png, image/webp, application/pdf,
 *     video/mp4, video/quicktime
 *
 * Wzorzec storage z SendHorseMessage (stable side); nie reuse'ujemy
 * bezpośrednio bo tamten ma stable-specific notification flow.
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 4 PR 4.2".
 */
class HorseMessageAttachmentStorage
{
    public const MAX_SIZE_BYTES = 25 * 1024 * 1024;   // 25 MB

    public const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
        'video/mp4',
        'video/quicktime',
    ];

    public const UPLOADER_OWNER = 'owner';

    public const UPLOADER_STABLE = 'stable';

    /**
     * Stores uploaded file with size + MIME validation. Returns metadata
     * which caller embeds w `attachments` JSON na HorseMessage.
     *
     * @return array{path: string, original_name: string, mime: string, size: int, uploader: string}
     *
     * @throws ValidationException przy invalid size / MIME
     */
    public function storeUploadedFile(
        Tenant $stableTenant,
        string $horseId,
        UploadedFile $file,
        string $uploaderRole = self::UPLOADER_OWNER,
    ): array {
        $this->validateUploaderRole($uploaderRole);
        $this->validateSize($file);
        $mime = $this->validateMime($file);

        $dir = "horse-messages/{$stableTenant->id}/{$horseId}";
        $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
        $filename = $uploaderRole.'-'.(string) Str::ulid().'_'.$sanitizedName;

        $path = $file->storeAs($dir, $filename, $this->disk());

        if ($path === false || $path === '') {
            throw new RuntimeException('Failed to store attachment file');
        }

        return [
            'path' => (string) $path,
            'original_name' => (string) $file->getClientOriginalName(),
            'mime' => $mime,
            'size' => (int) $file->getSize(),
            'uploader' => $uploaderRole,
        ];
    }

    /**
     * Stream pliku do response'a. Caller MUSI wcześniej zweryfikować
     * ownership (nie sprawdzamy tu — serwis storage nie ma kontekstu auth).
     *
     * @param  array{path: string, original_name?: string, mime?: string}  $attachment
     */
    public function streamFromAttachment(array $attachment): StreamedResponse
    {
        $path = (string) ($attachment['path'] ?? '');
        if ($path === '' || ! Storage::disk($this->disk())->exists($path)) {
            throw new RuntimeException('Attachment file not found');
        }

        $mime = (string) ($attachment['mime'] ?? 'application/octet-stream');
        $originalName = (string) ($attachment['original_name'] ?? basename($path));

        return Storage::disk($this->disk())->response($path, $originalName, [
            'Content-Type' => $mime,
        ]);
    }

    /**
     * Hard-delete pliku z dysku. Wywoływane gdy stable/owner usunie
     * wiadomość lub specific attachment. Idempotent — no-op gdy plik
     * już nie istnieje.
     */
    public function delete(string $path): void
    {
        if ($path === '') {
            return;
        }
        if (Storage::disk($this->disk())->exists($path)) {
            Storage::disk($this->disk())->delete($path);
        }
    }

    private function disk(): string
    {
        return (string) config('hovera.uploads.disk', 'local');
    }

    /**
     * Sprawdza czy `path` należy do tego stable tenant'a — zabezpieczenie
     * przed cross-tenant download'em gdy ktoś podstawi obcą ścieżkę.
     */
    public function pathBelongsToStable(string $path, Tenant $stableTenant): bool
    {
        $expectedPrefix = "horse-messages/{$stableTenant->id}/";

        return str_starts_with($path, $expectedPrefix);
    }

    private function validateSize(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw ValidationException::withMessages([
                'attachment' => __('owner/messages.attachment.too_large', [
                    'name' => $file->getClientOriginalName(),
                    'max_mb' => self::MAX_SIZE_BYTES / 1024 / 1024,
                ]),
            ]);
        }
    }

    private function validateMime(UploadedFile $file): string
    {
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages([
                'attachment' => __('owner/messages.attachment.unsupported_mime', [
                    'mime' => $mime,
                    'name' => $file->getClientOriginalName(),
                ]),
            ]);
        }

        return $mime;
    }

    private function validateUploaderRole(string $role): void
    {
        if (! in_array($role, [self::UPLOADER_OWNER, self::UPLOADER_STABLE], true)) {
            throw new RuntimeException("Invalid uploader role: {$role}");
        }
    }
}
