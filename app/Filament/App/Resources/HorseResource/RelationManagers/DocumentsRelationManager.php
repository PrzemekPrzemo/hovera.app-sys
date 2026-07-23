<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\HorseResource\RelationManagers;

use App\Enums\HorseDocumentKind;
use App\Models\Tenant\Horse;
use App\Models\Tenant\HorseDocument;
use App\Services\Stable\HorseDocumentService;
use App\Tenancy\TenantManager;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Tab "Dokumenty" w karcie konia.
 *
 * Stajnia może uploadować + edytować + usuwać. Widoczne tu są również
 * dokumenty wgrane przez właściciela (badge "Klient") — read-only dla
 * stajni (nie edytujemy cudzych uploadów).
 */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('models.documents');
    }

    public static function getModelLabel(): ?string
    {
        return __('models.document');
    }

    public static function getPluralModelLabel(): ?string
    {
        return __('models.documents');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('app/horse_document.form.label.name'))
                        ->placeholder(__('app/horse_document.form.label.name_placeholder'))
                        ->required()
                        ->maxLength(200),
                    Forms\Components\Select::make('kind')
                        ->label(__('app/horse_document.form.label.kind'))
                        ->options(HorseDocumentKind::options())
                        ->required(),
                ]),
            Forms\Components\Textarea::make('description')
                ->label(__('app/horse_document.form.label.description'))->rows(2),
            Forms\Components\FileUpload::make('file')
                ->label(__('app/horse_document.form.label.file'))
                ->required()
                ->maxSize(25 * 1024)
                ->acceptedFileTypes([
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'image/jpeg', 'image/png', 'image/webp', 'image/heic',
                ])
                ->disk($this->disk())
                ->visibility('private')
                ->storeFiles(false), // przekazujemy raw do uploadByStable
            Forms\Components\Grid::make(2)
                ->schema([
                    Forms\Components\DatePicker::make('valid_from')
                        ->label(__('app/horse_document.form.label.valid_from')),
                    Forms\Components\DatePicker::make('valid_until')
                        ->label(__('app/horse_document.form.label.valid_until')),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('kind')
                    ->label(__('app/horse_document.table.column.kind'))
                    ->formatStateUsing(fn (HorseDocumentKind $state) => $state->icon().' '.$state->label()),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('app/horse_document.table.column.name'))
                    ->searchable()->limit(50)->weight('bold'),
                Tables\Columns\TextColumn::make('original_name')
                    ->label(__('app/horse_document.table.column.original_name'))->limit(40)->toggleable(),
                Tables\Columns\TextColumn::make('size_bytes')
                    ->label(__('app/horse_document.table.column.size'))
                    ->formatStateUsing(fn (HorseDocument $r) => $r->sizeFormatted()),
                Tables\Columns\BadgeColumn::make('uploaded_by_role')
                    ->label(__('app/horse_document.table.column.uploaded_by'))
                    ->formatStateUsing(fn (string $state) => $state === 'stable'
                        ? __('app/horse_document.uploaded_by.stable')
                        : __('app/horse_document.uploaded_by.client'))
                    ->colors([
                        'primary' => 'stable',
                        'warning' => 'client',
                    ]),
                Tables\Columns\TextColumn::make('valid_until')
                    ->label(__('app/horse_document.table.column.valid_until'))
                    ->date()
                    ->placeholder('—')
                    ->color(fn (HorseDocument $r): string => match (true) {
                        $r->isExpired() => 'danger',
                        $r->isExpiringSoon(30) => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('app/horse_document.table.column.created_at'))->date()->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('kind')->options(HorseDocumentKind::options()),
                Tables\Filters\SelectFilter::make('uploaded_by_role')->options([
                    'stable' => __('app/horse_document.uploaded_by.stable'),
                    'client' => __('app/horse_document.uploaded_by.client'),
                ]),
                Tables\Filters\Filter::make('expiring_soon')
                    ->label(__('app/horse_document.table.filter.expiring_soon'))
                    ->query(fn ($query) => $query->expiringWithin(30)),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('app/horse_document.action.create.label'))
                    ->icon('heroicon-m-document-arrow-up')
                    ->using(function (array $data): HorseDocument {
                        /** @var Horse $horse */
                        $horse = $this->getOwnerRecord();
                        $tenant = app(TenantManager::class)->tenantOrFail();

                        $file = $this->normaliseUploadedFile($data['file'] ?? null);
                        if (! $file) {
                            throw ValidationException::withMessages(['file' => __('app/horse_document.action.create.no_file')]);
                        }

                        try {
                            return app(HorseDocumentService::class)->uploadByStable(
                                tenant: $tenant,
                                horse: $horse,
                                file: $file,
                                name: (string) $data['name'],
                                kind: HorseDocumentKind::from((string) $data['kind']),
                                description: ($data['description'] ?? '') !== '' ? (string) $data['description'] : null,
                                validFrom: $data['valid_from'] ?? null,
                                validUntil: $data['valid_until'] ?? null,
                                uploadedByUserId: Auth::id() ? (string) Auth::id() : null,
                            );
                        } catch (ValidationException $e) {
                            Notification::make()->title(__('app/horse_document.action.create.failed'))->body(
                                implode("\n", Arr::flatten($e->errors())),
                            )->danger()->send();
                            throw $e;
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label(__('app/horse_document.action.download.label'))
                    ->icon('heroicon-m-arrow-down-tray')
                    ->action(function (HorseDocument $record) {
                        return Storage::disk($this->disk())->download($record->file_path, $record->original_name);
                    }),
                Tables\Actions\EditAction::make()
                    ->visible(fn (HorseDocument $r) => $r->uploadedByStable())
                    ->using(function (HorseDocument $record, array $data): HorseDocument {
                        // Edycja tylko meta (nazwa, kategoria, opis, ważność) —
                        // nie zmieniamy pliku
                        $record->forceFill([
                            'name' => (string) ($data['name'] ?? $record->name),
                            'kind' => (string) ($data['kind'] ?? $record->kind->value),
                            'description' => ($data['description'] ?? '') !== '' ? (string) $data['description'] : null,
                            'valid_from' => $data['valid_from'] ?? null,
                            'valid_until' => $data['valid_until'] ?? null,
                        ])->save();

                        return $record;
                    }),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ]);
    }

    private function normaliseUploadedFile(mixed $file): ?UploadedFile
    {
        // Filament storeFiles(false) zwraca tablicę {0: UploadedFile}
        // dla single-file uploadu LUB samo UploadedFile.
        if (is_array($file)) {
            $file = reset($file);
        }
        if ($file instanceof UploadedFile) {
            return $file;
        }
        if ($file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return new UploadedFile(
                $file->getRealPath(),
                $file->getClientOriginalName(),
                $file->getMimeType(),
                null,
                true,
            );
        }

        return null;
    }

    private function disk(): string
    {
        return (string) config('hovera.uploads.disk', 'local');
    }
}
