<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\HorseResource\RelationManagers;

use App\Models\Tenant\Horse;
use App\Models\Tenant\HorsePhoto;
use App\Tenancy\TenantManager;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photo gallery RelationManager — JPG/PNG/WEBP/HEIC up to 10 MB each.
 * Files land in storage/app/private/tenants/{tenant}/horses/{horse}/photos/
 * to keep them off the public URL space; portal exposes them through an
 * authenticated streaming endpoint.
 */
class PhotosRelationManager extends RelationManager
{
    protected static string $relationship = 'photos';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('models.horse_photos');
    }

    public static function getModelLabel(): ?string
    {
        return __('models.horse_photo');
    }

    public static function getPluralModelLabel(): ?string
    {
        return __('models.horse_photos');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('caption')
                ->label(__('app/horse_photo.form.label.caption'))
                ->maxLength(255),
            Forms\Components\TextInput::make('sort_order')
                ->label(__('app/horse_photo.form.label.sort_order'))
                ->numeric()
                ->default(0),
            Forms\Components\FileUpload::make('file')
                ->label(__('app/horse_photo.form.label.file'))
                ->image()
                ->required()
                ->maxSize(10 * 1024) // 10 MB
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/heic'])
                ->disk('local')
                ->visibility('private')
                ->storeFiles(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('caption')
            ->columns([
                Tables\Columns\ImageColumn::make('file_path')
                    ->label(__('app/horse_photo.table.column.thumb'))
                    ->disk('local')
                    ->visibility('private')
                    ->square()
                    ->size(60),
                Tables\Columns\TextColumn::make('caption')
                    ->label(__('app/horse_photo.table.column.caption'))
                    ->placeholder('—')
                    ->limit(60),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('app/horse_photo.table.column.sort_order'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('size_bytes')
                    ->label(__('app/horse_photo.table.column.size'))
                    ->formatStateUsing(fn ($state, HorsePhoto $r) => $r->sizeFormatted())
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('app/horse_photo.table.column.created_at'))
                    ->date()
                    ->toggleable(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('app/horse_photo.action.upload'))
                    ->icon('heroicon-m-photo')
                    ->using(function (array $data): HorsePhoto {
                        /** @var Horse $horse */
                        $horse = $this->getOwnerRecord();
                        $tenant = app(TenantManager::class)->tenantOrFail();

                        $file = $this->normaliseUploadedFile($data['file'] ?? null);
                        if (! $file) {
                            abort(422, 'No file uploaded');
                        }

                        $directory = "tenants/{$tenant->slug}/horses/{$horse->id}/photos";
                        $storedPath = $file->store($directory, 'local');
                        if ($storedPath === false) {
                            abort(500, 'Failed to store photo');
                        }

                        return HorsePhoto::create([
                            'id' => (string) Str::ulid(),
                            'horse_id' => $horse->id,
                            'file_path' => $storedPath,
                            'original_name' => $file->getClientOriginalName(),
                            'mime' => $file->getMimeType() ?? 'application/octet-stream',
                            'size_bytes' => $file->getSize() ?: 0,
                            'caption' => $data['caption'] ?? null,
                            'sort_order' => (int) ($data['sort_order'] ?? 0),
                            'uploaded_by_role' => 'stable',
                            'uploaded_by_user_id' => auth()->id(),
                        ]);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (HorsePhoto $record): void {
                        if ($record->file_path) {
                            Storage::disk('local')->delete($record->file_path);
                        }
                    }),
            ]);
    }

    /**
     * Filament's FileUpload with storeFiles(false) returns either a single
     * UploadedFile or an array; normalise to a single UploadedFile|null.
     */
    private function normaliseUploadedFile(mixed $value): ?UploadedFile
    {
        if ($value instanceof UploadedFile) {
            return $value;
        }
        if (is_array($value)) {
            $first = reset($value);

            return $first instanceof UploadedFile ? $first : null;
        }

        return null;
    }
}
