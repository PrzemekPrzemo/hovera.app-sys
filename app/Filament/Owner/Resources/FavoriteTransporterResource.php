<?php

declare(strict_types=1);

namespace App\Filament\Owner\Resources;

use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Filament\Owner\Resources\FavoriteTransporterResource\Pages;
use App\Models\Central\OwnerFavoriteTransporter;
use App\Models\Central\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Owner panel: manage list "Ulubieni przewoźnicy". Owner dodaje
 * zaufanych transporterów po slug'u lub nazwie; lista jest używana
 * w `OrderTransport` przy targeted leadach.
 *
 * Scope: tylko własne wpisy (owner_user_id=Auth::id()). Forma dodawania
 * pokazuje verified transporterów do wyboru. Reject duplicatów przez
 * `firstOrCreate`.
 */
class FavoriteTransporterResource extends Resource
{
    protected static ?string $model = OwnerFavoriteTransporter::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?int $navigationSort = 25;

    public static function getNavigationLabel(): string
    {
        return __('owner/favorite_transporters.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('owner/favorite_transporters.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('owner/favorite_transporters.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('owner/favorite_transporters.model.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('owner_user_id', Auth::id() ?? '_no_user_')
            ->with('transporter:id,name,slug,verification_status');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('transporter_tenant_id')
                ->label(__('owner/favorite_transporters.form.transporter'))
                ->options(fn () => self::availableTransporterOptions())
                ->required()
                ->searchable()
                ->preload()
                ->disabledOn('edit')
                ->helperText(__('owner/favorite_transporters.form.transporter_helper')),
            Forms\Components\Textarea::make('notes')
                ->label(__('owner/favorite_transporters.form.notes'))
                ->placeholder(__('owner/favorite_transporters.form.notes_placeholder'))
                ->rows(2)
                ->maxLength(500),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transporter.name')
                    ->label(__('owner/favorite_transporters.table.name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('transporter.slug')
                    ->label(__('owner/favorite_transporters.table.slug'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('notes')
                    ->label(__('owner/favorite_transporters.table.notes'))
                    ->limit(50)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('owner/favorite_transporters.table.added'))
                    ->dateTime('Y-m-d')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('owner/favorite_transporters.empty.heading'))
            ->emptyStateDescription(__('owner/favorite_transporters.empty.description'))
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('owner/favorite_transporters.action.add'))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['id'] = (string) Str::ulid();
                        $data['owner_user_id'] = Auth::id();

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFavoriteTransporters::route('/'),
        ];
    }

    /**
     * Verified transporterzy do wyboru — owner widzi tylko zweryfikowanych
     * (anti-spam: nie polecaj losowych). Excluding już-dodanych — query
     * filtruje przez owner_user_id w whereDoesntHave.
     *
     * @return array<string,string>
     */
    private static function availableTransporterOptions(): array
    {
        $alreadyAdded = OwnerFavoriteTransporter::query()
            ->where('owner_user_id', Auth::id())
            ->pluck('transporter_tenant_id')
            ->all();

        return Tenant::query()
            ->where('type', TenantType::Transporter->value)
            ->where('verification_status', VerificationStatus::Verified->value)
            ->whereIn('status', Tenant::PANEL_ACCESSIBLE_STATUSES)
            ->whereNotIn('id', $alreadyAdded)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
