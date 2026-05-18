<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources;

use App\Filament\Concerns\RestrictedByTenantRole;
use App\Filament\Transport\Resources\DriverResource\Pages;
use App\Models\Tenant\Driver;
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

class DriverResource extends Resource
{
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::FULL_ADMINS_AND_MANAGERS;
    }

    protected static ?string $model = Driver::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.fleet');
    }

    public static function getNavigationLabel(): string
    {
        return __('navigation.drivers');
    }

    public static function getModelLabel(): string
    {
        return __('models.driver');
    }

    public static function getPluralModelLabel(): string
    {
        return __('models.drivers');
    }

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('transport/driver.section.personal'))
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('first_name')
                        ->label(__('transport/driver.form.label.first_name'))
                        ->required()
                        ->maxLength(60),
                    Forms\Components\TextInput::make('last_name')
                        ->label(__('transport/driver.form.label.last_name'))
                        ->required()
                        ->maxLength(80),
                    Forms\Components\DatePicker::make('date_of_birth')
                        ->label(__('transport/driver.form.label.date_of_birth'))
                        ->native(false)
                        ->maxDate(now()->subYears(18)),
                ]),

            Forms\Components\Section::make(__('transport/driver.section.contact'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('email')
                        ->label(__('transport/driver.form.label.email'))
                        ->email()
                        ->helperText(__('transport/driver.form.helper.email'))
                        ->maxLength(160),
                    Forms\Components\TextInput::make('phone')
                        ->label(__('transport/driver.form.label.phone'))
                        ->tel()
                        ->maxLength(40),
                ]),

            Forms\Components\Section::make(__('transport/driver.section.license'))
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('license_number')
                        ->label(__('transport/driver.form.label.license_number'))
                        ->maxLength(32),
                    Forms\Components\Select::make('license_categories')
                        ->label(__('transport/driver.form.label.license_categories'))
                        ->multiple()
                        ->options([
                            'B' => 'B',
                            'BE' => 'B+E',
                            'C1' => 'C1',
                            'C1E' => 'C1+E',
                            'C' => 'C',
                            'CE' => 'C+E',
                            'D1' => 'D1',
                            'D' => 'D',
                        ])
                        ->native(false),
                    Forms\Components\DatePicker::make('license_expires_at')
                        ->label(__('transport/driver.form.label.license_expires_at'))
                        ->native(false),
                ]),

            Forms\Components\Section::make(__('transport/driver.section.qualifications'))
                ->description(__('transport/driver.section.qualifications_description'))
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('has_animal_transport_cert')
                        ->label(__('transport/driver.form.label.has_animal_transport_cert'))
                        ->inline(false)
                        ->live(),
                    Forms\Components\DatePicker::make('animal_transport_cert_expires_at')
                        ->label(__('transport/driver.form.label.animal_transport_cert_expires_at'))
                        ->native(false)
                        ->visible(fn (Forms\Get $get) => (bool) $get('has_animal_transport_cert')),
                    Forms\Components\Toggle::make('has_adr')
                        ->label(__('transport/driver.form.label.has_adr'))
                        ->inline(false)
                        ->live(),
                    Forms\Components\DatePicker::make('adr_expires_at')
                        ->label(__('transport/driver.form.label.adr_expires_at'))
                        ->native(false)
                        ->visible(fn (Forms\Get $get) => (bool) $get('has_adr')),
                ]),

            Forms\Components\Section::make(__('transport/driver.section.other'))
                ->columns(2)
                ->schema([
                    Forms\Components\DatePicker::make('hire_date')
                        ->label(__('transport/driver.form.label.hire_date'))
                        ->native(false),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('transport/driver.form.label.is_active'))
                        ->default(true)
                        ->inline(false),
                    Forms\Components\TextInput::make('sort_order')
                        ->label(__('transport/driver.form.label.sort_order'))
                        ->numeric()
                        ->default(0),
                    Forms\Components\Textarea::make('notes')
                        ->label(__('transport/driver.form.label.notes'))
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label(__('transport/driver.table.column.full_name'))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['last_name']),
                Tables\Columns\TextColumn::make('phone')
                    ->label(__('transport/driver.table.column.phone'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('email')
                    ->label(__('transport/driver.table.column.email'))
                    ->toggleable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('license_expires_at')
                    ->label(__('transport/driver.table.column.license_expires_at'))
                    ->date()
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : ($state && $state->isBefore(now()->addDays(30)) ? 'warning' : null))
                    ->toggleable(),
                Tables\Columns\IconColumn::make('has_animal_transport_cert')
                    ->label(__('transport/driver.table.column.has_animal_transport_cert'))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('transport/driver.table.column.is_active'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('#')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('transport/driver.table.column.is_active')),
                Tables\Filters\Filter::make('license_expiring_soon')
                    ->label(__('transport/driver.filter.license_expiring_soon'))
                    ->query(fn (Builder $q) => $q->whereNotNull('license_expires_at')
                        ->whereBetween('license_expires_at', [now(), now()->addDays(30)])),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->after(self::auditCallback('driver.update')),
                Tables\Actions\DeleteAction::make()->after(self::auditCallback('driver.delete')),
                Tables\Actions\RestoreAction::make()->after(self::auditCallback('driver.restore')),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDrivers::route('/'),
            'create' => Pages\CreateDriver::route('/create'),
            'edit' => Pages\EditDriver::route('/{record}/edit'),
        ];
    }

    private static function auditCallback(string $action): callable
    {
        return function (Model $record) use ($action) {
            app(TenantAuditLogger::class)->record($action, 'Driver', (string) $record->getKey(), [
                'full_name' => $record->full_name,
            ]);
        };
    }
}
