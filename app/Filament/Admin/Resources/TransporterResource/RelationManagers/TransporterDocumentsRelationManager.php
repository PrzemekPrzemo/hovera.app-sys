<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TransporterResource\RelationManagers;

use App\Domain\Transport\Verification\VerificationChecklist;
use App\Domain\Transport\Verification\VerificationChecklistService;
use App\Enums\TransporterDocumentType;
use App\Models\Central\Tenant;
use App\Models\Tenant\TransporterDocument;
use App\Services\MasterAuditLogger;
use App\Tenancy\TenantManager;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Master admin widok dokumentów per-transporter. UWAGA: `TransporterDocument`
 * mieszka w per-tenant DB; `TransporterResource` jest w central. Przepinamy
 * connection przez `TenantManager::setCurrent($ownerRecord)` w `getTableQuery`.
 *
 * Master admin nie wgrywa dokumentów — tylko czyta + verify/reject każdego
 * pojedynczego dokumentu. Po zatwierdzeniu wszystkich wymaganych typów
 * `TransporterResource::verify()` odblokowuje się.
 *
 * Patrz docs/TRANSPORT.md §13 (master admin verification flow).
 */
class TransporterDocumentsRelationManager extends RelationManager
{
    // Relacja nie istnieje na modelu Tenant (TransporterDocument jest per-tenant),
    // ale Filament wymaga atrybutu. Używamy 'documents' jako placeholder —
    // getTableQuery() ignoruje ten property całkowicie.
    protected static string $relationship = 'documents';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin/transporter.documents.title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        // Pokazujemy tylko transporterom (Tenant.type=transporter).
        return $ownerRecord instanceof Tenant && $ownerRecord->isTransporter();
    }

    public function isReadOnly(): bool
    {
        // Wyłączamy CRUD form Filamenta — własne akcje verify/reject.
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            // Master admin nie wgrywa dokumentów — zwracamy minimalny form
            // dla zgodności (Filament wymaga). Akcje verify/reject mają osobne modale.
        ]);
    }

    public function table(Table $table): Table
    {
        $tenant = $this->getOwnerRecord();
        $this->setTenantContext($tenant);

        return $table
            ->query(function () use ($tenant) {
                // Re-set tenant context przy każdym rebuilcie query (Livewire może
                // wywołać kilka razy w ciągu lifecycle'a).
                $this->setTenantContext($tenant);

                return TransporterDocument::query();
            })
            ->columns([
                Tables\Columns\TextColumn::make('document_type')
                    ->label(__('admin/transporter.documents.column.type'))
                    ->formatStateUsing(fn ($state) => $state instanceof TransporterDocumentType
                        ? $state->label()
                        : (TransporterDocumentType::tryFrom((string) $state)?->label() ?? '—'))
                    ->wrap(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin/transporter.documents.column.status'))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        TransporterDocument::STATUS_VERIFIED => 'success',
                        TransporterDocument::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn ($state) => __('enums.verification_status.'.($state === 'verified' ? 'verified' : ($state === 'rejected' ? 'rejected' : 'pending')))),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('transport/documents.label.expires_at'))
                    ->date()
                    ->placeholder('—')
                    ->color(fn (TransporterDocument $r) => $r->isExpired()
                        ? 'danger'
                        : ($r->isExpiringSoon() ? 'warning' : null)),
                Tables\Columns\TextColumn::make('original_filename')
                    ->label(__('admin/transporter.documents.column.filename'))
                    ->limit(40)
                    ->tooltip(fn (TransporterDocument $r) => $r->original_filename),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin/transporter.documents.column.uploaded_at'))
                    ->dateTime()
                    ->since(),
                Tables\Columns\IconColumn::make('public_file_path')
                    ->label(__('admin/transporter.documents.column.public'))
                    ->boolean()
                    ->getStateUsing(fn (TransporterDocument $r) => $r->hasPublicVersion())
                    ->trueIcon('heroicon-o-globe-alt')
                    ->falseIcon('heroicon-o-no-symbol')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn (TransporterDocument $r) => $r->hasPublicVersion()
                        ? __('admin/transporter.documents.public.yes_tooltip')
                        : __('admin/transporter.documents.public.no_tooltip')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        TransporterDocument::STATUS_PENDING => __('enums.verification_status.pending'),
                        TransporterDocument::STATUS_VERIFIED => __('enums.verification_status.verified'),
                        TransporterDocument::STATUS_REJECTED => __('enums.verification_status.rejected'),
                    ]),
                Tables\Filters\SelectFilter::make('document_type')
                    ->options(TransporterDocumentType::options()),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('preview_doc')
                    ->label(__('admin/transporter.documents.action.preview'))
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->openUrlInNewTab()
                    ->url(fn (TransporterDocument $r) => route('admin.transporter.document.preview', [
                        'tenant' => $tenant->id,
                        'document' => $r->id,
                    ])),
                Tables\Actions\Action::make('download_doc')
                    ->label(__('admin/transporter.documents.action.download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (TransporterDocument $r) => route('admin.transporter.document.download', [
                        'tenant' => $tenant->id,
                        'document' => $r->id,
                    ])),
                Tables\Actions\Action::make('upload_anonymized')
                    ->label(__('admin/transporter.documents.action.upload_anonymized'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->visible(fn (TransporterDocument $r) => $r->status === TransporterDocument::STATUS_VERIFIED)
                    ->modalDescription(__('admin/transporter.documents.upload_anonymized.modal_description'))
                    ->form([
                        Forms\Components\FileUpload::make('public_file')
                            ->label(__('admin/transporter.documents.upload_anonymized.file_label'))
                            ->required()
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->maxSize(5120) // 5MB — public profile, niech będzie lekkie
                            ->disk((string) config('transport.documents.disk', 'local'))
                            ->directory(fn () => 'transporter-docs/'.$tenant->id.'/public')
                            ->preserveFilenames(false)
                            ->helperText(__('admin/transporter.documents.upload_anonymized.helper')),
                    ])
                    ->action(function (TransporterDocument $r, array $data) use ($tenant) {
                        $this->setTenantContext($tenant);
                        $disk = Storage::disk((string) config('transport.documents.disk', 'local'));

                        // Filament zapisał plik pod ścieżką w `public_file` —
                        // może być string (single path) lub array (multi upload).
                        $uploaded = is_array($data['public_file'])
                            ? (string) reset($data['public_file'])
                            : (string) $data['public_file'];

                        // Wyciągamy meta bezpośrednio z dysku — UploadedFile
                        // Filament już zwolnił (post-action).
                        $size = $disk->size($uploaded);
                        $mime = $disk->mimeType($uploaded) ?: 'application/octet-stream';

                        // Stary zanonimizowany plik (jeśli był) usuwamy — admin
                        // wgrał nową wersję, chcemy clean tracking.
                        if (! empty($r->public_file_path) && $r->public_file_path !== $uploaded) {
                            $disk->delete($r->public_file_path);
                        }

                        $r->forceFill([
                            'public_file_path' => $uploaded,
                            'public_file_size' => $size,
                            'public_file_mime' => $mime,
                            'public_uploaded_at' => now(),
                            'public_uploaded_by_user_id' => Auth::id() !== null ? (string) Auth::id() : null,
                        ])->save();

                        app(MasterAuditLogger::class)->record(
                            action: 'transporter_document.public_uploaded',
                            targetType: 'TransporterDocument',
                            targetId: (string) $r->id,
                            tenantId: (string) $tenant->id,
                            payload: ['type' => $r->document_type?->value, 'size' => $size],
                        );

                        Notification::make()
                            ->success()
                            ->title(__('admin/transporter.documents.notify.public_uploaded'))
                            ->send();
                    }),
                Tables\Actions\Action::make('remove_anonymized')
                    ->label(__('admin/transporter.documents.action.remove_anonymized'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (TransporterDocument $r) => ! empty($r->public_file_path))
                    ->requiresConfirmation()
                    ->modalDescription(__('admin/transporter.documents.remove_anonymized.modal_description'))
                    ->action(function (TransporterDocument $r) use ($tenant) {
                        $this->setTenantContext($tenant);
                        if (! empty($r->public_file_path)) {
                            Storage::disk((string) config('transport.documents.disk', 'local'))
                                ->delete($r->public_file_path);
                        }
                        $r->forceFill([
                            'public_file_path' => null,
                            'public_file_size' => null,
                            'public_file_mime' => null,
                            'public_uploaded_at' => null,
                            'public_uploaded_by_user_id' => null,
                        ])->save();

                        app(MasterAuditLogger::class)->record(
                            action: 'transporter_document.public_removed',
                            targetType: 'TransporterDocument',
                            targetId: (string) $r->id,
                            tenantId: (string) $tenant->id,
                            payload: ['type' => $r->document_type?->value],
                        );

                        Notification::make()
                            ->warning()
                            ->title(__('admin/transporter.documents.notify.public_removed'))
                            ->send();
                    }),
                Tables\Actions\Action::make('verify_doc')
                    ->label(__('transport/documents.admin.verify_doc'))
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->visible(fn (TransporterDocument $r) => $r->status !== TransporterDocument::STATUS_VERIFIED)
                    ->requiresConfirmation()
                    ->modalDescription(__('transport/documents.admin.verify_doc_confirm'))
                    ->action(function (TransporterDocument $r) use ($tenant) {
                        $this->setTenantContext($tenant);
                        $r->forceFill([
                            'status' => TransporterDocument::STATUS_VERIFIED,
                            'verified_by_user_id' => Auth::id(),
                            'verified_at' => now(),
                            'rejection_reason' => null,
                        ])->save();

                        app(MasterAuditLogger::class)->record(
                            action: 'transporter_document.verify',
                            targetType: 'TransporterDocument',
                            targetId: (string) $r->id,
                            tenantId: (string) $tenant->id,
                            payload: ['type' => $r->document_type?->value],
                        );

                        Notification::make()
                            ->success()
                            ->title(__('transport/documents.admin.notify_doc_verified'))
                            ->send();
                    }),
                Tables\Actions\Action::make('reject_doc')
                    ->label(__('transport/documents.admin.reject_doc'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (TransporterDocument $r) => $r->status !== TransporterDocument::STATUS_REJECTED)
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label(__('transport/documents.admin.rejection_reason_required'))
                            ->rows(3)
                            ->required(),
                    ])
                    ->action(function (TransporterDocument $r, array $data) use ($tenant) {
                        $this->setTenantContext($tenant);
                        $r->forceFill([
                            'status' => TransporterDocument::STATUS_REJECTED,
                            'verified_by_user_id' => Auth::id(),
                            'verified_at' => null,
                            'rejection_reason' => (string) $data['reason'],
                        ])->save();

                        app(MasterAuditLogger::class)->record(
                            action: 'transporter_document.reject',
                            targetType: 'TransporterDocument',
                            targetId: (string) $r->id,
                            tenantId: (string) $tenant->id,
                            payload: ['type' => $r->document_type?->value, 'reason_excerpt' => mb_substr((string) $data['reason'], 0, 120)],
                        );

                        Notification::make()
                            ->warning()
                            ->title(__('transport/documents.admin.notify_doc_rejected'))
                            ->send();
                    }),
            ]);
    }

    private function setTenantContext(Tenant $tenant): void
    {
        try {
            app(TenantManager::class)->setCurrent($tenant);
        } catch (\Throwable $e) {
            // W testach feature bez tenant DB — quietly fail; query zwróci empty.
            report($e);
        }
    }

    /**
     * Helper do widoku Tenant edit page: zwraca checklistę. Wywoływane spoza
     * tej klasy (TransporterResource::table verify action) — wymaga
     * setTenantContext przed.
     */
    public static function checklistForTenant(Tenant $tenant): VerificationChecklist
    {
        app(TenantManager::class)->setCurrent($tenant);

        return app(VerificationChecklistService::class)->build();
    }
}
