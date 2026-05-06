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

    protected static ?string $navigationGroup = 'Kalendarz';

    protected static ?string $navigationLabel = 'Ujeżdżalnie';

    protected static ?string $modelLabel = 'ujeżdżalnia';

    protected static ?string $pluralModelLabel = 'Ujeżdżalnie';

    protected static ?int $navigationSort = 50;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nazwa')->required()->maxLength(120),
            Forms\Components\Select::make('type')
                ->label('Typ')
                ->options(Arena::typeOptions())
                ->default('indoor')
                ->required(),
            Forms\Components\ColorPicker::make('color')->label('Kolor w kalendarzu'),
            Forms\Components\Toggle::make('is_active')->label('Aktywna')->default(true),
            Forms\Components\TextInput::make('sort_order')->label('Kolejność')->numeric()->default(0),
            Forms\Components\Textarea::make('notes')->label('Notatki')->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nazwa')->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('type')->label('Typ')
                    ->formatStateUsing(fn (?string $state) => Arena::typeOptions()[$state] ?? $state),
                Tables\Columns\ColorColumn::make('color')->label('Kolor')->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->label('Aktywna')->boolean(),
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
