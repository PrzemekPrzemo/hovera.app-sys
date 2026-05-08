<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\SpecialistResource\Pages;
use App\Models\Central\TenantMembership;
use App\Models\Tenant\Specialist;
use App\Tenancy\TenantManager;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SpecialistResource extends Resource
{
    protected static ?string $model = Specialist::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.stable');
    }

    public static function getNavigationLabel(): string
    {
        return __('navigation.specialists');
    }

    public static function getModelLabel(): string
    {
        return __('models.specialist');
    }

    public static function getPluralModelLabel(): string
    {
        return __('models.specialists');
    }

    protected static ?int $navigationSort = 26;

    /** @return array<string,string> */
    public static function typeOptions(): array
    {
        return [
            Specialist::TYPE_VET => __('app/specialist.types.vet'),
            Specialist::TYPE_FARRIER => __('app/specialist.types.farrier'),
        ];
    }

    /** @return array<string,string> */
    public static function typeOptionsShort(): array
    {
        return [
            Specialist::TYPE_VET => __('app/specialist.types_short.vet'),
            Specialist::TYPE_FARRIER => __('app/specialist.types_short.farrier'),
        ];
    }

    /**
     * Active stable members (owners/admins/employees) — possible
     * candidates to link a Specialist to. Built from central
     * memberships filtered to the current tenant.
     *
     * @return array<string,string>
     */
    private static function memberOptions(): array
    {
        $tenant = app(TenantManager::class)->current();
        if (! $tenant) {
            return [];
        }

        return TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->whereNull('revoked_at')
            ->with('user:id,name,email')
            ->get()
            ->mapWithKeys(fn (TenantMembership $m) => [
                $m->user_id => $m->user
                    ? trim($m->user->name.' ('.$m->user->email.')')
                    : $m->user_id,
            ])
            ->all();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('app/specialist.form.section.data'))
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('type')
                        ->label(__('app/specialist.form.label.type'))
                        ->options(self::typeOptions())
                        ->required(),
                    Forms\Components\TextInput::make('name')
                        ->label(__('app/specialist.form.label.name'))
                        ->required()->maxLength(255),
                    Forms\Components\TextInput::make('email')->email()->maxLength(255),
                    Forms\Components\TextInput::make('phone')
                        ->label(__('app/specialist.form.label.phone'))
                        ->tel()->maxLength(40),
                    Forms\Components\ColorPicker::make('color')
                        ->label(__('app/specialist.form.label.color')),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('app/specialist.form.label.is_active'))
                        ->default(true),
                    Forms\Components\TextInput::make('sort_order')
                        ->label(__('app/specialist.form.label.sort_order'))
                        ->numeric()->default(0),
                    Forms\Components\Textarea::make('notes')
                        ->label(__('app/specialist.form.label.notes'))
                        ->rows(3)->columnSpanFull(),
                ]),
            Forms\Components\Section::make(__('app/specialist.form.section.access'))
                ->description(__('app/specialist.form.section.access_description'))
                ->collapsed()
                ->schema([
                    Forms\Components\Select::make('central_user_id')
                        ->label(__('app/specialist.form.label.central_user'))
                        ->options(fn () => self::memberOptions())
                        ->placeholder(__('app/specialist.form.label.central_user_placeholder'))
                        ->searchable()
                        ->helperText(__('app/specialist.form.helper.central_user')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('type')
                    ->label(__('app/specialist.table.column.type'))
                    ->formatStateUsing(fn (string $state) => self::typeOptions()[$state] ?? $state)
                    ->colors([
                        'success' => Specialist::TYPE_VET,
                        'primary' => Specialist::TYPE_FARRIER,
                    ]),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('app/specialist.table.column.name'))
                    ->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label(__('app/specialist.table.column.phone'))->toggleable(),
                Tables\Columns\IconColumn::make('central_user_id')
                    ->label(__('app/specialist.table.column.central_user'))
                    ->getStateUsing(fn (Specialist $r) => $r->central_user_id !== null)
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-minus'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('app/specialist.table.column.is_active'))->boolean(),
                Tables\Columns\ColorColumn::make('color')->toggleable(),
            ])
            ->defaultSort('type')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label(__('app/specialist.table.filter.type'))
                    ->options(self::typeOptions()),
                Tables\Filters\Filter::make('has_account')
                    ->label(__('app/specialist.table.filter.has_account'))
                    ->query(fn (Builder $q) => $q->whereNotNull('central_user_id')),
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
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSpecialists::route('/'),
            'create' => Pages\CreateSpecialist::route('/create'),
            'edit' => Pages\EditSpecialist::route('/{record}/edit'),
        ];
    }
}
