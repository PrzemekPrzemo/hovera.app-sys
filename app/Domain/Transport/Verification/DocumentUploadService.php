<?php

declare(strict_types=1);

namespace App\Domain\Transport\Verification;

use App\Enums\TransporterDocumentType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\TransporterDocument;
use App\Tenancy\TenantManager;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Upload + lifecycle dokumentów weryfikacyjnych transportera. Trzyma plik
 * w storage (disk z `transport.documents.disk`, default 'local'), tworzy/
 * aktualizuje wpis w `transporter_documents`. Po wgraniu wszystkich
 * wymaganych typów (5 required) flipuje Tenant.verification_status z
 * pending na under_review — sygnał dla master admin'a "do sprawdzenia".
 */
class DocumentUploadService
{
    public const ALLOWED_MIMES = ['application/pdf', 'image/jpeg', 'image/png'];

    public const MAX_SIZE_BYTES = 10 * 1024 * 1024;   // 10 MB

    public function __construct(
        private readonly TenantManager $tenants,
    ) {}

    public function upload(
        UploadedFile $file,
        TransporterDocumentType $type,
        ?Carbon $expiresAt = null,
        ?Carbon $issuedAt = null,
    ): TransporterDocument {
        $this->assertAllowed($file);

        $tenant = $this->tenants->tenantOrFail();
        $disk = $this->disk();

        // Replace existing (latest) document of same type if status=pending
        // or rejected — keeps history via soft deletes when status=verified
        // (verified docs nie nadpisujemy, tworzymy NOWY rekord żeby zachować audyt).
        $existing = TransporterDocument::query()
            ->where('document_type', $type->value)
            ->whereIn('status', [TransporterDocument::STATUS_PENDING, TransporterDocument::STATUS_REJECTED])
            ->latest('id')
            ->first();

        $path = $disk->putFileAs(
            "transporter-docs/{$tenant->id}",
            $file,
            $type->value.'-'.Str::random(12).'.'.$file->guessClientExtension(),
        );

        if ($existing) {
            // Stary plik fizycznie usuwamy — pending/rejected tracking nie wymaga.
            if ($existing->file_path) {
                $disk->delete($existing->file_path);
            }
            $existing->forceFill([
                'status' => TransporterDocument::STATUS_PENDING,
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'file_mime' => $file->getMimeType(),
                'original_filename' => $file->getClientOriginalName(),
                'expires_at' => $expiresAt?->toDateString(),
                'issued_at' => $issuedAt?->toDateString(),
                'rejection_reason' => null,
                'verified_at' => null,
                'verified_by_user_id' => null,
            ])->save();

            $document = $existing;
        } else {
            $document = TransporterDocument::create([
                'id' => (string) Str::ulid(),
                'document_type' => $type,
                'status' => TransporterDocument::STATUS_PENDING,
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'file_mime' => $file->getMimeType(),
                'original_filename' => $file->getClientOriginalName(),
                'expires_at' => $expiresAt?->toDateString(),
                'issued_at' => $issuedAt?->toDateString(),
            ]);
        }

        $this->maybePromoteToUnderReview($tenant);

        return $document;
    }

    /**
     * Usuwa dokument (soft delete + plik fizyczny) — tylko gdy nie verified.
     * Po usunięciu może obniżyć status tenant'a z under_review→pending.
     */
    public function destroy(TransporterDocument $document): void
    {
        $tenant = $this->tenants->tenantOrFail();

        if ($document->status === TransporterDocument::STATUS_VERIFIED) {
            throw new \RuntimeException('Nie można usunąć zweryfikowanego dokumentu — skontaktuj się z hovera.');
        }

        if ($document->file_path) {
            $this->disk()->delete($document->file_path);
        }
        $document->delete();

        $this->maybeDemoteToPending($tenant);
    }

    /**
     * Aktualne dokumenty per typ (1 lub 0 — najnowszy active z konkretnego typu).
     *
     * @return array<string, TransporterDocument|null>  klucz = TransporterDocumentType::value
     */
    public function latestByType(): array
    {
        $map = [];
        foreach (TransporterDocumentType::cases() as $type) {
            $map[$type->value] = TransporterDocument::query()
                ->where('document_type', $type->value)
                ->latest('id')
                ->first();
        }

        return $map;
    }

    /**
     * Reguła auto-flip: wszystkie wymagane typy mają wgrany dokument w status
     * pending lub verified → flip Tenant.verification_status na under_review.
     * Master admin musi explicitly potwierdzić → verified.
     */
    public function maybePromoteToUnderReview(Tenant $tenant): bool
    {
        if ($tenant->verification_status !== VerificationStatus::Pending) {
            return false;
        }

        if (! $this->hasAllRequired()) {
            return false;
        }

        $tenant->forceFill(['verification_status' => VerificationStatus::UnderReview])->save();

        return true;
    }

    private function maybeDemoteToPending(Tenant $tenant): void
    {
        if ($tenant->verification_status !== VerificationStatus::UnderReview) {
            return;
        }

        if ($this->hasAllRequired()) {
            return;
        }

        $tenant->forceFill(['verification_status' => VerificationStatus::Pending])->save();
    }

    /**
     * Reguła weryfikacji PWL: wszystkie typy `isRequiredForPwlVerification()`
     * muszą być wgrane w status pending/verified, z wyjątkiem pary T1/T2
     * — która jest alternatywą (transporter wybiera dokładnie jeden zależnie
     * od profilu transportów, < 8h vs > 8h). KRS/CEIDG jest legacy-required
     * przez `isRequired()` (zachowane dla wstecznej kompatybilności).
     */
    public function hasAllRequired(): bool
    {
        // KRS / CEIDG — legacy required (część `requiredCases()`).
        if (! $this->hasDocumentOfType(TransporterDocumentType::CompanyRegistration)) {
            return false;
        }

        // PWL: każdy typ poza T1/T2.
        foreach (TransporterDocumentType::pwlRequiredCases() as $type) {
            if (in_array($type, [
                TransporterDocumentType::PwlAuthorizationT1,
                TransporterDocumentType::PwlAuthorizationT2,
            ], true)) {
                continue;
            }
            if (! $this->hasDocumentOfType($type)) {
                return false;
            }
        }

        // T1 albo T2 — co najmniej jeden.
        return $this->hasPwlAuthorization();
    }

    /**
     * Czy transporter ma wgrane co najmniej jedno z autoryzacji PWL T1/T2.
     */
    public function hasPwlAuthorization(): bool
    {
        return $this->hasDocumentOfType(TransporterDocumentType::PwlAuthorizationT1)
            || $this->hasDocumentOfType(TransporterDocumentType::PwlAuthorizationT2);
    }

    /**
     * Czy wszystkie wymagane PWL dokumenty są STATUS_VERIFIED (nie tylko wgrane).
     * Używane przez master admin'a — blokuje verify tenanta dopóki nie ma
     * pełnego zatwierdzonego kompletu.
     */
    public function hasAllRequiredVerified(): bool
    {
        // KRS / CEIDG
        if (! $this->hasVerifiedDocumentOfType(TransporterDocumentType::CompanyRegistration)) {
            return false;
        }

        foreach (TransporterDocumentType::pwlRequiredCases() as $type) {
            if (in_array($type, [
                TransporterDocumentType::PwlAuthorizationT1,
                TransporterDocumentType::PwlAuthorizationT2,
            ], true)) {
                continue;
            }
            if (! $this->hasVerifiedDocumentOfType($type)) {
                return false;
            }
        }

        // T1 lub T2 musi być verified
        return $this->hasVerifiedDocumentOfType(TransporterDocumentType::PwlAuthorizationT1)
            || $this->hasVerifiedDocumentOfType(TransporterDocumentType::PwlAuthorizationT2);
    }

    private function hasDocumentOfType(TransporterDocumentType $type): bool
    {
        return TransporterDocument::query()
            ->where('document_type', $type->value)
            ->whereIn('status', [TransporterDocument::STATUS_PENDING, TransporterDocument::STATUS_VERIFIED])
            ->exists();
    }

    private function hasVerifiedDocumentOfType(TransporterDocumentType $type): bool
    {
        return TransporterDocument::query()
            ->where('document_type', $type->value)
            ->where('status', TransporterDocument::STATUS_VERIFIED)
            ->exists();
    }

    private function assertAllowed(UploadedFile $file): void
    {
        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw new \RuntimeException(__('transport/documents.error.bad_mime', [
                'allowed' => 'PDF, JPG, PNG',
            ]));
        }
        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw new \RuntimeException(__('transport/documents.error.too_large', [
                'limit' => (int) (self::MAX_SIZE_BYTES / 1024 / 1024).' MB',
            ]));
        }
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('transport.documents.disk', 'local'));
    }
}
