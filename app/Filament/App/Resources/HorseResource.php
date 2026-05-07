<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\HorseResource\Pages;
use App\Models\Tenant\BoardingService;
use App\Models\Tenant\Box;
use App\Models\Tenant\Client;
use App\Models\Tenant\Horse;
use App\Services\TenantAuditLogger;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class HorseResource extends Resource
{
    protected static ?string $model = Horse::class;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationGroup = 'Stajnia';

    protected static ?string $navigationLabel = 'Konie';

    protected static ?string $modelLabel = 'koń';

    protected static ?string $pluralModelLabel = 'Konie';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identyfikacja')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')->label('Imię')->required()->maxLength(120),
                    Forms\Components\Select::make('owner_client_id')
                        ->label('Właściciel')
                        ->options(fn () => Client::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->placeholder('— stajnia —'),
                    Forms\Components\Select::make('box_id')
                        ->label('Box')
                        ->options(fn () => Box::query()
                            ->where('is_active', true)
                            ->orderBy('sort_order')
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->placeholder('— bez przypisania —')
                        ->helperText('Zmiana boxa zarejestruje historię w "Boxy → Historia przypisań".'),
                    Forms\Components\TextInput::make('microchip')->maxLength(32),
                    Forms\Components\TextInput::make('passport_number')->label('Nr paszportu')->maxLength(64),
                    Forms\Components\TextInput::make('ueln')->label('UELN')->maxLength(15)
                        ->helperText('Universal Equine Life Number'),
                ]),

            Forms\Components\Section::make('Charakterystyka')
                ->columns(4)
                ->schema([
                    Forms\Components\Select::make('sex')->label('Płeć')->options([
                        'mare' => 'Klacz',
                        'stallion' => 'Ogier',
                        'gelding' => 'Wałach',
                        'filly' => 'Klaczka',
                        'colt' => 'Ogierek',
                        'foal' => 'Źrebię',
                    ]),
                    Forms\Components\TextInput::make('breed')->label('Rasa')->maxLength(120),
                    Forms\Components\TextInput::make('color')->label('Maść')->maxLength(60),
                    Forms\Components\DatePicker::make('birth_date')->label('Data urodzenia'),
                ]),

            Forms\Components\Section::make('Pensja — usługi naliczane')
                ->description('Zaznacz które pozycje cennika dotyczą tego konia. Klient zobaczy je w portalu z miesięczną szacunkową kwotą.')
                ->collapsed()
                ->schema([
                    Forms\Components\Select::make('boardingServices')
                        ->label('Usługi z cennika')
                        ->multiple()
                        ->relationship('boardingServices', 'name')
                        ->options(fn () => BoardingService::query()
                            ->where('is_active', true)
                            ->orderBy('sort_order')
                            ->pluck('name', 'id'))
                        ->preload()
                        ->searchable()
                        ->helperText('Cennik konfigurujesz w "Stajnia → Cennik pensji". Override ceny per koń (np. zniżka) ustawiasz tam ręcznie po utworzeniu wpisu.'),
                ]),

            Forms\Components\Section::make('Notatki')
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('notes')->rows(4),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Imię')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('breed')->label('Rasa')->toggleable()->searchable(),
                Tables\Columns\BadgeColumn::make('sex')
                    ->label('Płeć')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'mare' => 'Klacz',
                        'stallion' => 'Ogier',
                        'gelding' => 'Wałach',
                        'filly' => 'Klaczka',
                        'colt' => 'Ogierek',
                        'foal' => 'Źrebię',
                        default => '—',
                    }),
                Tables\Columns\TextColumn::make('color')->label('Maść')->toggleable(),
                Tables\Columns\TextColumn::make('birth_date')->label('Ur.')->date()->sortable(),
                Tables\Columns\TextColumn::make('owner.name')->label('Właściciel')->placeholder('— stajnia —')->toggleable(),
                Tables\Columns\TextColumn::make('microchip')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->label('Dodany')->date()->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('sex')->options([
                    'mare' => 'Klacz',
                    'stallion' => 'Ogier',
                    'gelding' => 'Wałach',
                    'filly' => 'Klaczka',
                    'colt' => 'Ogierek',
                    'foal' => 'Źrebię',
                ]),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->after(self::auditCallback('horse.update')),
                Tables\Actions\DeleteAction::make()->after(self::auditCallback('horse.delete')),
                Tables\Actions\RestoreAction::make()->after(self::auditCallback('horse.restore')),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getRelations(): array
    {
        return [
            HorseResource\RelationManagers\HealthRecordsRelationManager::class,
            HorseResource\RelationManagers\ActivitiesRelationManager::class,
            HorseResource\RelationManagers\MessagesRelationManager::class,
            HorseResource\RelationManagers\DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHorses::route('/'),
            'create' => Pages\CreateHorse::route('/create'),
            'edit' => Pages\EditHorse::route('/{record}/edit'),
        ];
    }

    private static function auditCallback(string $action): callable
    {
        return function (Model $record) use ($action) {
            app(TenantAuditLogger::class)->record($action, 'Horse', (string) $record->getKey(), [
                'name' => $record->name,
            ]);
        };
    }
}
