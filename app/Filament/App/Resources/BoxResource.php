<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\BoxResource\Pages;
use App\Filament\Components\PriceInput;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Models\Tenant\Box;
use App\Models\Tenant\Building;
use App\Services\Tenancy\TenantRoleGate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BoxResource extends Resource
{
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::STABLE_OPS_STAFF;
    }

    protected static ?string $model = Box::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.stable');
    }

    public static function getNavigationLabel(): string
    {
        return __('navigation.boxes');
    }

    public static function getModelLabel(): string
    {
        return __('models.box');
    }

    public static function getPluralModelLabel(): string
    {
        return __('models.boxes');
    }

    protected static ?int $navigationSort = 35;

    /** @return array<string,string> */
    private static function typeOptions(): array
    {
        return [
            'indoor' => __('app/box.types.indoor'),
            'paddock' => __('app/box.types.paddock'),
            'outdoor' => __('app/box.types.outdoor'),
            'quarantine' => __('app/box.types.quarantine'),
        ];
    }

    /** @return array<string,string> */
    private static function typeOptionsShort(): array
    {
        return [
            'indoor' => __('app/box.types_short.indoor'),
            'paddock' => __('app/box.types_short.paddock'),
            'outdoor' => __('app/box.types_short.outdoor'),
            'quarantine' => __('app/box.types_short.quarantine'),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('app/box.form.section.box'))
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('building_id')
                        ->label(__('app/box.form.label.building'))
                        ->options(fn () => Building::query()
                            ->where('is_active', true)
                            ->orderBy('sort_order')->orderBy('name')
                            ->pluck('name', 'id'))
                        ->placeholder(__('app/box.form.label.building_placeholder'))
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('name')
                        ->label(__('app/box.form.label.name'))
                        ->required()->maxLength(60),
                    Forms\Components\TextInput::make('label')
                        ->label(__('app/box.form.label.label_short'))
                        ->maxLength(20),
                    Forms\Components\Select::make('type')
                        ->label(__('app/box.form.label.type'))
                        ->options(self::typeOptions())
                        ->default('indoor')
                        ->required(),
                    Forms\Components\TextInput::make('size_m2')
                        ->label(__('app/box.form.label.size_m2'))
                        ->numeric()->minValue(1)->maxValue(500),
                    Forms\Components\Hidden::make('capacity')->default(1),
                    PriceInput::make('monthly_rate_cents', __('app/box.form.label.monthly_rate'))
                        ->helperText(__('app/box.form.helper.monthly_rate')),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('app/box.form.label.is_active'))
                        ->default(true),
                    Forms\Components\TextInput::make('sort_order')
                        ->label(__('app/box.form.label.sort_order'))
                        ->numeric()->default(0),
                ]),
            Forms\Components\Section::make(__('app/box.form.section.notes'))
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->label(__('app/box.form.label.notes'))
                        ->rows(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('building.name')
                    ->label(__('app/box.table.column.building'))
                    ->placeholder(__('app/box.table.column.building_none'))
                    ->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('app/box.table.column.name'))
                    ->searchable()->sortable()->weight('bold'),
                Tables\Columns\BadgeColumn::make('type')
                    ->label(__('app/box.table.column.type'))
                    ->formatStateUsing(fn (string $state) => self::typeOptionsShort()[$state] ?? $state)
                    ->colors([
                        'primary' => 'indoor',
                        'success' => 'paddock',
                        'gray' => 'outdoor',
                        'warning' => 'quarantine',
                    ]),
                Tables\Columns\TextColumn::make('size_m2')
                    ->label(__('app/box.table.column.size_m2'))
                    ->placeholder('—')->sortable()->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('app/box.table.column.status'))
                    ->getStateUsing(fn (Box $r) => $r->horses->isNotEmpty() ? 'occupied' : 'free')
                    ->formatStateUsing(fn (string $state) => $state === 'free'
                        ? __('app/box.table.status.free')
                        : __('app/box.table.status.occupied'))
                    ->colors([
                        'success' => 'free',
                        'gray' => 'occupied',
                    ]),
                Tables\Columns\TextColumn::make('horse_sex')
                    ->label(__('app/box.table.column.horse_sex'))
                    ->getStateUsing(function (Box $r) {
                        $horse = $r->horses->first();

                        return $horse?->sex ? HorseResource::sexOptions()[$horse->sex] ?? null : null;
                    })
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('monthly_rate_cents')
                    ->label(__('app/box.table.column.monthly_rate'))
                    ->formatStateUsing(fn (?int $state) => $state !== null ? number_format($state / 100, 2, ',', ' ').' zł' : '—')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('app/box.table.column.is_active'))
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->groups([
                Tables\Grouping\Group::make('building.name')
                    ->label(__('app/box.table.column.building'))
                    ->collapsible(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('building_id')
                    ->label(__('app/box.table.filter.building'))
                    ->relationship('building', 'name')
                    ->searchable()->preload(),
                Tables\Filters\SelectFilter::make('type')->options(self::typeOptionsShort()),
                Tables\Filters\Filter::make('vacant')
                    ->label(__('app/box.table.filter.vacant'))
                    ->query(fn (Builder $q) => $q->whereDoesntHave('horses')),
                Tables\Filters\Filter::make('only_active')
                    ->label(__('app/box.table.filter.only_active'))
                    ->query(fn (Builder $q) => $q->where('is_active', true))
                    ->default(),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with([
                'building:id,name',
                'horses' => fn ($q) => $q->select('id', 'box_id', 'sex'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBoxes::route('/'),
            'create' => Pages\CreateBox::route('/create'),
            'edit' => Pages\EditBox::route('/{record}/edit'),
        ];
    }
}
