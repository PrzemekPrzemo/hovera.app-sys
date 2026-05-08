<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\ArenaResource\Pages;
use App\Models\Tenant\Arena;
use App\Services\TenantAuditLogger;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ArenaResource extends Resource
{
    protected static ?string $model = Arena::class;

    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.calendar');
    }

    public static function getNavigationLabel(): string
    {
        return __('navigation.arenas');
    }

    public static function getModelLabel(): string
    {
        return __('models.arena');
    }

    public static function getPluralModelLabel(): string
    {
        return __('models.arenas');
    }

    protected static ?int $navigationSort = 50;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label(__('app/arena.form.label.name'))->required()->maxLength(120),
            Forms\Components\Select::make('type')
                ->label(__('app/arena.form.label.type'))
                ->options(Arena::typeOptions())
                ->default('indoor')
                ->required(),
            Forms\Components\ColorPicker::make('color')
                ->label(__('app/arena.form.label.color')),
            Forms\Components\Toggle::make('is_active')
                ->label(__('app/arena.form.label.is_active'))->default(true),
            Forms\Components\TextInput::make('sort_order')
                ->label(__('app/arena.form.label.sort_order'))->numeric()->default(0),
            Forms\Components\Textarea::make('notes')
                ->label(__('app/arena.form.label.notes'))->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('app/arena.table.column.name'))->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->label(__('app/arena.table.column.type'))
                    ->formatStateUsing(fn (?string $state) => Arena::typeOptions()[$state] ?? $state),
                Tables\Columns\ColorColumn::make('color')
                    ->label(__('app/arena.table.column.color'))->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('app/arena.table.column.is_active'))->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('#')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(Arena::typeOptions()),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->after(self::auditCallback('arena.update')),
                Tables\Actions\DeleteAction::make()->after(self::auditCallback('arena.delete')),
                Tables\Actions\RestoreAction::make()->after(self::auditCallback('arena.restore')),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArenas::route('/'),
            'create' => Pages\CreateArena::route('/create'),
            'edit' => Pages\EditArena::route('/{record}/edit'),
        ];
    }

    private static function auditCallback(string $action): callable
    {
        return function (Model $record) use ($action) {
            app(TenantAuditLogger::class)->record($action, 'Arena', (string) $record->getKey(), [
                'name' => $record->name,
            ]);
        };
    }
}
