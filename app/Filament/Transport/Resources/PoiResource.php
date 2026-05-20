<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources;

use App\Filament\Concerns\RestrictedByTenantRole;
use App\Filament\Transport\Resources\PoiResource\Pages;
use App\Models\Tenant\Poi;
use App\Services\Tenancy\TenantRoleGate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * POI library transportera — bibliotek punktów interesujących (bazy,
 * stajnie, parkingi, paliwo). Reuse'owalne w Calculator + QuoteResource
 * jako waypointy. Patrz docs/MARKETPLACE-ROADMAP.md "Waypoints + POI library".
 *
 * Lat/lng są Hidden — geokoduje je CreatePoi/EditPoi w mutateFormDataBeforeSave
 * przez MapboxGeocoder. Soft-fail: gdy geocoding padnie, user dostaje
 * notification + lat/lng są 0,0 (po edycji adresu można retry).
 */
class PoiResource extends Resource
{
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::TRANSPORT_OPERATORS;
    }

    protected static ?string $model = Poi::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.fleet');
    }

    public static function getNavigationLabel(): string
    {
        return __('transport/poi.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('transport/poi.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('transport/poi.model.plural');
    }

    protected static ?int $navigationSort = 30;

    /** @return array<string,string> */
    public static function kindOptions(): array
    {
        return [
            Poi::KIND_BASE => __('transport/poi.kind.base'),
            Poi::KIND_STABLE => __('transport/poi.kind.stable'),
            Poi::KIND_PARKING => __('transport/poi.kind.parking'),
            Poi::KIND_FUEL => __('transport/poi.kind.fuel'),
            Poi::KIND_OTHER => __('transport/poi.kind.other'),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('transport/poi.section.basic'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('transport/poi.form.label.name'))
                        ->required()
                        ->maxLength(120),
                    Forms\Components\Select::make('kind')
                        ->label(__('transport/poi.form.label.kind'))
                        ->options(self::kindOptions())
                        ->default(Poi::KIND_OTHER)
                        ->required()
                        ->native(false),
                    Forms\Components\TextInput::make('address')
                        ->label(__('transport/poi.form.label.address'))
                        ->helperText(__('transport/poi.form.helper.address'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull()
                        ->extraInputAttributes(['data-places-autocomplete' => 'panel', 'autocomplete' => 'off']),
                    // Lat/lng są geokodowane w lifecycle hook'u (Create/Edit).
                    Forms\Components\Hidden::make('lat')->default(0),
                    Forms\Components\Hidden::make('lng')->default(0),
                ]),

            Forms\Components\Section::make(__('transport/poi.section.metadata'))
                ->columns(2)
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->label(__('transport/poi.form.label.notes'))
                        ->rows(3)
                        ->columnSpanFull()
                        ->maxLength(2000),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('transport/poi.form.label.is_active'))
                        ->default(true)
                        ->inline(false),
                    Forms\Components\TextInput::make('sort_order')
                        ->label(__('transport/poi.form.label.sort_order'))
                        ->numeric()
                        ->default(0),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('transport/poi.table.column.name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('kind')
                    ->label(__('transport/poi.table.column.kind'))
                    ->formatStateUsing(fn (?string $state) => $state === null ? '—' : (self::kindOptions()[$state] ?? $state))
                    ->colors([
                        'primary' => Poi::KIND_BASE,
                        'success' => Poi::KIND_STABLE,
                        'warning' => Poi::KIND_PARKING,
                        'gray' => fn ($state) => in_array($state, [Poi::KIND_FUEL, Poi::KIND_OTHER], true),
                    ]),
                Tables\Columns\TextColumn::make('address')
                    ->label(__('transport/poi.table.column.address'))
                    ->limit(50)
                    ->tooltip(fn (Poi $r) => $r->address),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('transport/poi.table.column.is_active'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('#')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('kind')
                    ->label(__('transport/poi.table.column.kind'))
                    ->options(self::kindOptions()),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('transport/poi.table.column.is_active')),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->emptyStateHeading(__('transport/poi.empty.heading'))
            ->emptyStateDescription(__('transport/poi.empty.description'))
            ->emptyStateIcon('heroicon-o-map-pin');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPois::route('/'),
            'create' => Pages\CreatePoi::route('/create'),
            'edit' => Pages\EditPoi::route('/{record}/edit'),
        ];
    }
}
