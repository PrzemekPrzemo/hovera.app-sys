<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Enums\PassStatus;
use App\Filament\App\Resources\PassResource\Pages;
use App\Filament\Components\PriceInput;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Models\Tenant\Client;
use App\Models\Tenant\Pass;
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

class PassResource extends Resource
{
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::FINANCE_STAFF;
    }

    protected static ?string $model = Pass::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.stable');
    }

    public static function getNavigationLabel(): string
    {
        return __('navigation.passes');
    }

    public static function getModelLabel(): string
    {
        return __('models.pass');
    }

    public static function getPluralModelLabel(): string
    {
        return __('models.passes');
    }

    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('app/pass.form.section.pass'))
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('client_id')
                        ->label(__('app/pass.form.label.client'))
                        ->options(fn () => Client::query()->orderBy('name')->pluck('name', 'id'))
                        ->required()
                        ->searchable(),
                    Forms\Components\TextInput::make('name')
                        ->label(__('app/pass.form.label.name'))
                        ->required()
                        ->maxLength(120)
                        ->placeholder(__('app/pass.form.label.name_placeholder')),
                    Forms\Components\TextInput::make('total_uses')
                        ->label(__('app/pass.form.label.total_uses'))
                        ->numeric()
                        ->minValue(1)
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(fn ($state, Forms\Set $set, ?Pass $record) => ! $record
                            ? $set('remaining_uses', $state)
                            : null),
                    Forms\Components\TextInput::make('remaining_uses')
                        ->label(__('app/pass.form.label.remaining_uses'))
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->helperText(__('app/pass.form.helper.remaining_uses')),
                    Forms\Components\DatePicker::make('valid_from')
                        ->label(__('app/pass.form.label.valid_from')),
                    Forms\Components\DatePicker::make('valid_until')
                        ->label(__('app/pass.form.label.valid_until')),
                    PriceInput::make('price_cents', __('app/pass.form.label.price')),
                    Forms\Components\TextInput::make('cancellation_policy_hours')
                        ->label(__('app/pass.form.label.cancellation_policy_hours'))
                        ->numeric()
                        ->minValue(0)
                        ->placeholder(__('app/pass.form.label.cancellation_policy_placeholder'))
                        ->helperText(__('app/pass.form.helper.cancellation_policy_hours')),
                    Forms\Components\Select::make('status')
                        ->label(__('app/pass.form.label.status'))
                        ->options(PassStatus::options())
                        ->default(PassStatus::Active->value)
                        ->required(),
                ]),
            Forms\Components\Textarea::make('notes')
                ->label(__('app/pass.form.label.notes'))->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client.name')
                    ->label(__('app/pass.table.column.client'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('app/pass.table.column.name'))->searchable(),
                Tables\Columns\TextColumn::make('remaining_uses')
                    ->label(__('app/pass.table.column.remaining_uses'))
                    ->formatStateUsing(fn (Pass $r) => "{$r->remaining_uses} / {$r->total_uses}")
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('app/pass.table.column.status'))
                    ->formatStateUsing(fn (PassStatus $state) => $state->label())
                    ->colors([
                        'success' => fn ($state) => $state === PassStatus::Active->value,
                        'gray' => fn ($state) => $state === PassStatus::Exhausted->value,
                        'warning' => fn ($state) => $state === PassStatus::Expired->value,
                        'danger' => fn ($state) => $state === PassStatus::Cancelled->value,
                    ]),
                Tables\Columns\TextColumn::make('valid_until')
                    ->label(__('app/pass.table.column.valid_until'))->date()->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('price_cents')
                    ->label(__('app/pass.table.column.price'))
                    ->formatStateUsing(fn (?int $state) => $state !== null ? number_format($state / 100, 2, ',', ' ').' zł' : '—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('cancellation_policy_hours')
                    ->label(__('app/pass.table.column.cancellation_policy'))
                    ->formatStateUsing(fn (?int $state) => $state !== null ? "{$state} h" : __('app/pass.table.column.cancellation_policy_default'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('app/pass.table.column.created_at'))->date()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(PassStatus::options()),
                Tables\Filters\SelectFilter::make('client_id')
                    ->label(__('app/pass.table.filter.client'))
                    ->relationship('client', 'name')
                    ->searchable(),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->after(self::auditCallback('pass.update')),
                Tables\Actions\DeleteAction::make()->after(self::auditCallback('pass.delete')),
                Tables\Actions\RestoreAction::make()->after(self::auditCallback('pass.restore')),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPasses::route('/'),
            'create' => Pages\CreatePass::route('/create'),
            'edit' => Pages\EditPass::route('/{record}/edit'),
        ];
    }

    private static function auditCallback(string $action): callable
    {
        return function (Model $record) use ($action) {
            app(TenantAuditLogger::class)->record($action, 'Pass', (string) $record->getKey(), [
                'name' => $record->name,
            ]);
        };
    }
}
