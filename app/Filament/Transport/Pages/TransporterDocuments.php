<?php

declare(strict_types=1);

namespace App\Filament\Transport\Pages;

use App\Domain\Transport\Verification\DocumentUploadService;
use App\Domain\Transport\Verification\VerificationChecklistService;
use App\Enums\TransporterDocumentType;
use App\Enums\VerificationStatus;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Models\Tenant\TransporterDocument;
use App\Services\Tenancy\TenantRoleGate;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Livewire\WithFileUploads;

/**
 * Panel weryfikacji konta transportera. 6 sekcji (po typie dokumentu),
 * w każdej upload + akcje zarządzania. Po wgraniu wszystkich required
 * typów Tenant.verification_status flipuje na under_review.
 *
 * Patrz docs/TRANSPORT.md (verification flow z feedbacku produkcyjnego).
 */
class TransporterDocuments extends Page implements HasForms
{
    use InteractsWithForms;
    use RestrictedByTenantRole;
    use WithFileUploads;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::FULL_ADMINS;
    }

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    public static function getNavigationLabel(): string
    {
        return __('transport/documents.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.settings');
    }

    public function getTitle(): string|Htmlable
    {
        return __('transport/documents.title');
    }

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.transport.pages.transporter-documents';

    /**
     * Live upload bindings — jeden slot per typ dokumentu.
     * Klucze = TransporterDocumentType::value.
     *
     * @var array<string, UploadedFile|null>
     */
    public array $files = [];

    /** @var array<string, string|null> */
    public array $expiresAt = [];

    /** @var array<string, string|null> */
    public array $issuedAt = [];

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);

        // Iterujemy po wszystkich case'ach — także po deprecated — bo użytkownik
        // może mieć stary dokument w DB którego chce zarządzić. UI ukrywa
        // niepotrzebne sloty (uiCases) + sekcję „legacy" pokazuje tylko gdy
        // tenant ma jakieś starsze rekordy.
        foreach (TransporterDocumentType::cases() as $type) {
            $this->files[$type->value] = null;
            $this->expiresAt[$type->value] = null;
            $this->issuedAt[$type->value] = null;
        }
    }

    public function uploadDocument(string $typeValue): void
    {
        abort_unless(self::canAccess(), 403);

        $type = TransporterDocumentType::from($typeValue);
        $file = $this->files[$typeValue] ?? null;

        if ($file === null) {
            $this->notifyError(__('transport/documents.error.no_file'));

            return;
        }

        try {
            $document = app(DocumentUploadService::class)->upload(
                $file,
                $type,
                $this->expiresAt[$typeValue] ? Carbon::parse($this->expiresAt[$typeValue]) : null,
                $this->issuedAt[$typeValue] ? Carbon::parse($this->issuedAt[$typeValue]) : null,
            );
        } catch (\Throwable $e) {
            $this->notifyError($e->getMessage());

            return;
        }

        app(TenantAuditLogger::class)->record(
            'transporter_document.upload',
            'TransporterDocument',
            (string) $document->id,
            ['type' => $type->value, 'mime' => $file->getMimeType()],
        );

        $this->files[$typeValue] = null;
        $this->expiresAt[$typeValue] = null;
        $this->issuedAt[$typeValue] = null;

        Notification::make()
            ->success()
            ->title(__('transport/documents.notify.uploaded'))
            ->body($type->label())
            ->send();
    }

    public function deleteDocument(string $documentId): void
    {
        abort_unless(self::canAccess(), 403);

        $document = TransporterDocument::query()->where('id', $documentId)->first();
        if (! $document) {
            return;
        }

        try {
            app(DocumentUploadService::class)->destroy($document);
        } catch (\Throwable $e) {
            $this->notifyError($e->getMessage());

            return;
        }

        app(TenantAuditLogger::class)->record(
            'transporter_document.delete',
            'TransporterDocument',
            $documentId,
            ['type' => $document->document_type?->value],
        );

        Notification::make()
            ->warning()
            ->title(__('transport/documents.notify.deleted'))
            ->send();
    }

    /**
     * Dane do widoku — aktualne dokumenty per typ + status weryfikacji konta
     * + flaga "ile typów still missing".
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $service = app(DocumentUploadService::class);
        $tenant = app(TenantManager::class)->tenantOrFail();
        $byType = $service->latestByType();
        $checklist = app(VerificationChecklistService::class)->build();

        // „Brakuje X" — w panelu transportera prosty licznik pendingów + missingów.
        // Reguła PWL nie liczy T1 i T2 osobno (alternatywa) — bierzemy z checklisty.
        $missingRequired = $checklist->totalRequired - $checklist->verifiedCount;

        // Sekcje UI: rozróżniamy required PWL od opcjonalnych i legacy.
        $pwlRequired = [];
        $optional = [];
        $legacy = [];

        foreach (TransporterDocumentType::cases() as $type) {
            if ($type === TransporterDocumentType::Other) {
                $optional[] = $type;

                continue;
            }
            if ($type->isDeprecated()) {
                // Pokazuj tylko gdy tenant faktycznie ma stary dokument w DB.
                if (($byType[$type->value] ?? null) !== null) {
                    $legacy[] = $type;
                }

                continue;
            }
            if ($type->isRequiredForPwlVerification() || $type === TransporterDocumentType::CompanyRegistration) {
                $pwlRequired[] = $type;

                continue;
            }
            $optional[] = $type;
        }

        return [
            'tenant' => $tenant,
            'documentTypes' => TransporterDocumentType::uiCases(),
            'pwlRequiredTypes' => $pwlRequired,
            'optionalTypes' => $optional,
            'legacyTypes' => $legacy,
            'docs' => $byType,
            'missingRequired' => $missingRequired,
            'verificationStatus' => $tenant->verification_status ?? VerificationStatus::Pending,
            'checklist' => $checklist,
        ];
    }

    private function notifyError(string $message): void
    {
        Notification::make()
            ->danger()
            ->title(__('transport/documents.notify.error'))
            ->body($message)
            ->persistent()
            ->send();
    }
}
