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

    protected static ?string $title = 'Dokumenty';

    protected static ?string $modelLabel = 'dokument';

    protected static ?string $pluralModelLabel = 'Dokumenty';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nazwa dokumentu')
                        ->placeholder('np. Paszport Bucefała')
                        ->required()
                        ->maxLength(200),
                    Forms\Components\Select::make('kind')
                        ->label('Kategoria')
                        ->options(HorseDocumentKind::options())
                        ->required(),
                ]),
            Forms\Components\Textarea::make('description')->label('Opis (opcjonalnie)')->rows(2),
            Forms\Components\FileUpload::make('file')
                ->label('Plik (max 25 MB)')
                ->required()
                ->maxSize(25 * 1024)
                ->acceptedFileTypes([
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'image/jpeg', 'image/png', 'image/webp', 'image/heic',
                ])
                ->disk('local')
                ->visibility('private')
                ->storeFiles(false), // przekazujemy raw do uploadByStable
            Forms\Components\Grid::make(2)
                ->schema([
                    Forms\Components\DatePicker::make('valid_from')->label('Ważny od (opcjonalne)'),
                    Forms\Components\DatePicker::make('valid_until')->label('Ważny do (opcjonalne)'),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('kind')
                    ->label('Kategoria')
                    ->formatStateUsing(fn (HorseDocumentKind $state) => $state->icon().' '.$state->label()),
                Tables\Columns\TextColumn::make('name')->label('Nazwa')->searchable()->limit(50)->weight('bold'),
                Tables\Columns\TextColumn::make('original_name')->label('Plik')->limit(40)->toggleable(),
                Tables\Columns\TextColumn::make('size_bytes')
                    ->label('Rozmiar')
                    ->formatStateUsing(fn (HorseDocument $r) => $r->sizeFormatted()),
                Tables\Columns\BadgeColumn::make('uploaded_by_role')
                    ->label('Wgrał')
                    ->formatStateUsing(fn (string $state) => $state === 'stable' ? 'Stajnia' : 'Klient')
                    ->colors([
                        'primary' => 'stable',
                        'warning' => 'client',
                    ]),
                Tables\Columns\TextColumn::make('valid_until')
                    ->label('Ważny do')
                    ->date()
                    ->placeholder('—')
                    ->color(fn (HorseDocument $r): string => match (true) {
                        $r->isExpired() => 'danger',
                        $r->isExpiringSoon(30) => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->label('Wgrany')->date()->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('kind')->options(HorseDocumentKind::options()),
                Tables\Filters\SelectFilter::make('uploaded_by_role')->options([
                    'stable' => 'Stajnia',
                    'client' => 'Klient',
                ]),
                Tables\Filters\Filter::make('expiring_soon')
                    ->label('Wygasa w 30 dni')
                    ->query(fn ($q) => $q->expiringWithin(30)),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Wgraj dokument')
                    ->icon('heroicon-m-document-arrow-up')
                    ->using(function (array $data): HorseDocument {
                        /** @var Horse $horse */
                        $horse = $this->getOwnerRecord();
                        $tenant = app(TenantManager::class)->tenantOrFail();

                        $file = $this->normaliseUploadedFile($data['file'] ?? null);
                        if (! $file) {
                            throw ValidationException::withMessages(['file' => 'Brak pliku.']);
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
                            Notification::make()->title('Nie udało się wgrać')->body(
                                implode("\n", Arr::flatten($e->errors())),
                            )->danger()->send();
                            throw $e;
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('Pobierz')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->action(function (HorseDocument $record) {
                        return Storage::disk('local')->download($record->file_path, $record->original_name);
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
}
