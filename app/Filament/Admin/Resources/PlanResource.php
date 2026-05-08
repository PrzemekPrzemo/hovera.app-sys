<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PlanResource\Pages;
use App\Filament\Components\PriceInput;
use App\Models\Central\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('navigation.plans');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.configuration');
    }

    public static function getModelLabel(): string
    {
        return __('models.plan');
    }

    public static function getPluralModelLabel(): string
    {
        return __('models.plans');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identyfikacja')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->required()
                        ->alphaDash()
                        ->maxLength(32)
                        ->unique(ignoreRecord: true)
                        ->helperText('Unikalny identyfikator (np. free, stable, pro). Używany w API + linkach.'),
                    Forms\Components\TextInput::make('name')->required()->maxLength(120),
                    Forms\Components\Select::make('currency')
                        ->options(['PLN' => 'PLN', 'EUR' => 'EUR', 'USD' => 'USD'])
                        ->default('PLN')
                        ->required(),
                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Niższe = wyżej na liście.'),
                ]),
            Forms\Components\Section::make('Cennik')
                ->columns(2)
                ->schema([
                    PriceInput::make('price_monthly_cents', 'Cena miesięczna'),
                    PriceInput::make('price_yearly_cents', 'Cena roczna')
                        ->helperText('Zwykle 10× miesięczna minus 10-30% zniżki rocznej.'),
                ]),
            Forms\Components\Section::make('Limity')
                ->description('Twarde limity planu — egzekwowane w aplikacji (CreateTenant blokuje gdy plan przekroczony).')
                ->schema([
                    Forms\Components\KeyValue::make('limits')
                        ->keyLabel('Klucz')
                        ->valueLabel('Wartość')
                        ->reorderable(false)
                        ->helperText('Standardowe klucze: max_horses, max_clients, max_users, max_storage_mb. -1 = bez limitu.'),
                ]),
            Forms\Components\Section::make('Funkcjonalności')
                ->description('Lista marketingowych bullet pointów + flag fukcjonalnych dla feature-flag system.')
                ->schema([
                    Forms\Components\KeyValue::make('features')
                        ->keyLabel('Klucz')
                        ->valueLabel('Wartość')
                        ->reorderable(false)
                        ->helperText('Klucze: bullets[N]=string (marketing), enabled.X=bool (feature flag).'),
                ]),
            Forms\Components\Section::make('Widoczność')
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label('Aktywny')
                        ->default(true)
                        ->helperText('Czy plan można nadal przypisać do nowych tenantów.'),
                    Forms\Components\Toggle::make('is_public')
                        ->label('Publiczny w cenniku')
                        ->default(true)
                        ->helperText('Czy pokazać na publicznej stronie cennika. Enterprise zwykle false (custom).'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('code')->searchable()->sortable()->copyable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('price_monthly_cents')
                    ->label('Miesięcznie')
                    ->formatStateUsing(fn (?int $state, Plan $r): string => $state === null ? '—' : number_format($state / 100, 0, ',', ' ').' '.$r->currency),
                Tables\Columns\TextColumn::make('price_yearly_cents')
                    ->label('Rocznie')
                    ->formatStateUsing(fn (?int $state, Plan $r): string => $state === null ? '—' : number_format($state / 100, 0, ',', ' ').' '.$r->currency),
                Tables\Columns\TextColumn::make('tenants_count')
                    ->label('Stajnie')
                    ->counts('tenants'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Akt.'),
                Tables\Columns\IconColumn::make('is_public')->boolean()->label('Publ.'),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->before(function (Plan $record) {
                    if ($record->tenants()->exists()) {
                        Notification::make()
                            ->danger()
                            ->title('Nie można usunąć — plan jest używany.')
                            ->body($record->tenants()->count().' stajni jest na tym planie. Najpierw przypisz inny plan.')
                            ->send();
                        $this->halt();
                    }
                }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PlanResource\RelationManagers\AddonsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
