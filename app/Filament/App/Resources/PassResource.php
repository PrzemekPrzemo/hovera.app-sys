<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Enums\PassStatus;
use App\Filament\App\Resources\PassResource\Pages;
use App\Filament\Components\PriceInput;
use App\Models\Tenant\Client;
use App\Models\Tenant\Pass;
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
    protected static ?string $model = Pass::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Stajnia';

    protected static ?string $navigationLabel = 'Karnety';

    protected static ?string $modelLabel = 'karnet';

    protected static ?string $pluralModelLabel = 'Karnety';

    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Karnet')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('client_id')
                        ->label('Klient')
                        ->options(fn () => Client::query()->orderBy('name')->pluck('name', 'id'))
                        ->required()
                        ->searchable(),
                    Forms\Components\TextInput::make('name')
                        ->label('Nazwa')
                        ->required()
                        ->maxLength(120)
                        ->placeholder('Karnet 8 jazd'),
                    Forms\Components\TextInput::make('total_uses')
                        ->label('Liczba jazd')
                        ->numeric()
                        ->minValue(1)
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(fn ($state, Forms\Set $set, ?Pass $record) => ! $record
                            ? $set('remaining_uses', $state)
                            : null),
                    Forms\Components\TextInput::make('remaining_uses')
                        ->label('Pozostało')
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->helperText('Auto-aktualizowane przez system; ręczna zmiana tylko w wyjątkowych sytuacjach.'),
                    Forms\Components\DatePicker::make('valid_from')->label('Ważny od'),
                    Forms\Components\DatePicker::make('valid_until')->label('Ważny do'),
                    PriceInput::make('price_cents', 'Cena karnetu'),
                    Forms\Components\TextInput::make('cancellation_policy_hours')
                        ->label('Polityka odwołania (h)')
                        ->numeric()
                        ->minValue(0)
                        ->placeholder('użyj domyślnej z ustawień stajni')
                        ->helperText('Odwołanie X godzin przed jazdą = bez kosztu (karnet wraca).'),
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options(PassStatus::options())
                        ->default(PassStatus::Active->value)
                        ->required(),
                ]),
            Forms\Components\Textarea::make('notes')->label('Notatki')->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client.name')->label('Klient')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Karnet')->searchable(),
                Tables\Columns\TextColumn::make('remaining_uses')
                    ->label('Pozostało')
                    ->formatStateUsing(fn (Pass $r) => "{$r->remaining_uses} / {$r->total_uses}")
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (PassStatus $state) => $state->label())
                    ->colors([
                        'success' => fn ($state) => $state === PassStatus::Active->value,
                        'gray' => fn ($state) => $state === PassStatus::Exhausted->value,
                        'warning' => fn ($state) => $state === PassStatus::Expired->value,
                        'danger' => fn ($state) => $state === PassStatus::Cancelled->value,
                    ]),
                Tables\Columns\TextColumn::make('valid_until')->label('Ważny do')->date()->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('price_cents')
                    ->label('Cena')
                    ->formatStateUsing(fn (?int $state) => $state !== null ? number_format($state / 100, 2, ',', ' ').' zł' : '—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('cancellation_policy_hours')
                    ->label('Odwołanie')
                    ->formatStateUsing(fn (?int $state) => $state !== null ? "{$state} h" : 'wg ustawień stajni')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->label('Wystawiony')->date()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(PassStatus::options()),
                Tables\Filters\SelectFilter::make('client_id')
                    ->label('Klient')
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
