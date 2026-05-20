<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources;

use App\Enums\VehicleType;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Filament\Transport\Resources\VehicleResource\Pages;
use App\Models\Tenant\Vehicle;
use App\Services\Tenancy\TenantRoleGate;
use App\Services\TenantAuditLogger;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VehicleResource extends Resource
{
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::TRANSPORT_OPERATORS;
    }

    protected static ?string $model = Vehicle::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.fleet');
    }

    public static function getNavigationLabel(): string
    {
        return __('navigation.vehicles');
    }

    public static function getModelLabel(): string
    {
        return __('models.vehicle');
    }

    public static function getPluralModelLabel(): string
    {
        return __('models.vehicles');
    }

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('transport/vehicle.section.identification'))
                ->schema([
                    Forms\Components\Select::make('vehicle_type')
                        ->label(__('transport/vehicle.form.label.vehicle_type'))
                        ->helperText(__('transport/vehicle.form.helper.vehicle_type'))
                        ->options(VehicleType::options())
                        ->default(VehicleType::Truck->value)
                        ->required()
                        ->native(false),
                    Forms\Components\TextInput::make('name')
                        ->label(__('transport/vehicle.form.label.name'))
                        ->required()
                        ->maxLength(120)
                        ->placeholder(__('transport/vehicle.form.placeholder.name')),
                    Forms\Components\TextInput::make('registration_plate')
                        ->label(__('transport/vehicle.form.label.registration_plate'))
                        ->required()
                        ->maxLength(16),
                    Forms\Components\TextInput::make('year_of_manufacture')
                        ->label(__('transport/vehicle.form.label.year_of_manufacture'))
                        ->numeric()
                        ->minValue(1980)
                        ->maxValue((int) date('Y') + 1),
                ])->columns(2),

            Forms\Components\Section::make(__('transport/vehicle.section.capacity'))
                ->schema([
                    Forms\Components\TextInput::make('capacity_horses')
                        ->label(__('transport/vehicle.form.label.capacity_horses'))
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(15)
                        ->suffix(__('transport/vehicle.form.suffix.horses')),
                    Forms\Components\TextInput::make('gross_weight_kg')
                        ->label(__('transport/vehicle.form.label.gross_weight_kg'))
                        ->numeric()
                        ->suffix('kg'),
                    Forms\Components\TextInput::make('payload_kg')
                        ->label(__('transport/vehicle.form.label.payload_kg'))
                        ->numeric()
                        ->suffix('kg'),
                ])->columns(3),

            Forms\Components\Section::make(__('transport/vehicle.section.equipment'))
                ->schema([
                    Forms\Components\Toggle::make('has_air_suspension')
                        ->label(__('transport/vehicle.form.label.has_air_suspension')),
                    Forms\Components\Toggle::make('has_camera')
                        ->label(__('transport/vehicle.form.label.has_camera')),
                    Forms\Components\Toggle::make('has_climate_control')
                        ->label(__('transport/vehicle.form.label.has_climate_control')),
                ])->columns(3),

            Forms\Components\Section::make(__('transport/vehicle.section.other'))
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('transport/vehicle.form.label.is_active'))
                        ->default(true)
                        ->inline(false),
                    Forms\Components\TextInput::make('sort_order')
                        ->label(__('transport/vehicle.form.label.sort_order'))
                        ->numeric()
                        ->default(0),
                    Forms\Components\Textarea::make('notes')
                        ->label(__('transport/vehicle.form.label.notes'))
                        ->rows(3)
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('vehicle_type')
                    ->label(__('transport/vehicle.table.column.vehicle_type'))
                    ->badge()
                    ->formatStateUsing(fn (VehicleType $state) => $state->label())
                    ->color(fn (VehicleType $state) => $state === VehicleType::Trailer ? 'warning' : 'info'),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('transport/vehicle.table.column.name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('registration_plate')
                    ->label(__('transport/vehicle.table.column.registration_plate'))
                    ->searchable()
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('capacity_horses')
                    ->label(__('transport/vehicle.table.column.capacity_horses'))
                    ->sortable()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('gross_weight_kg')
                    ->label(__('transport/vehicle.table.column.gross_weight_kg'))
                    ->suffix(' kg')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('transport/vehicle.table.column.is_active'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('vehicle_type')
                    ->label(__('transport/vehicle.table.column.vehicle_type'))
                    ->options(VehicleType::options()),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('transport/vehicle.table.column.is_active')),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->after(self::auditCallback('vehicle.update')),
                Tables\Actions\DeleteAction::make()->after(self::auditCallback('vehicle.delete')),
                Tables\Actions\RestoreAction::make()->after(self::auditCallback('vehicle.restore')),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVehicles::route('/'),
            'create' => Pages\CreateVehicle::route('/create'),
            'edit' => Pages\EditVehicle::route('/{record}/edit'),
        ];
    }

    private static function auditCallback(string $action): callable
    {
        return function (Model $record) use ($action) {
            app(TenantAuditLogger::class)->record($action, 'Vehicle', (string) $record->getKey(), [
                'name' => $record->name,
                'registration_plate' => $record->registration_plate,
            ]);
        };
    }
}
