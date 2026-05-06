<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\InstructorResource\Pages;
use App\Models\Tenant\Instructor;
use App\Services\TenantAuditLogger;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InstructorResource extends Resource
{
    protected static ?string $model = Instructor::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationGroup = 'Kalendarz';

    protected static ?string $navigationLabel = 'Instruktorzy';

    protected static ?string $modelLabel = 'instruktor';

    protected static ?string $pluralModelLabel = 'Instruktorzy';

    protected static ?int $navigationSort = 60;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dane instruktora')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')->label('Imię i nazwisko')->required()->maxLength(255),
                    Forms\Components\TextInput::make('email')->email()->maxLength(255),
                    Forms\Components\TextInput::make('phone')->label('Telefon')->tel()->maxLength(40),
                    Forms\Components\TextInput::make('hourly_rate_cents')
                        ->label('Stawka za godzinę (gr)')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('W groszach. 8000 = 80 zł.'),
                    Forms\Components\ColorPicker::make('color')->label('Kolor w kalendarzu'),
                    Forms\Components\Toggle::make('is_active')->label('Aktywny')->default(true),
                ]),
            Forms\Components\Textarea::make('notes')->label('Notatki')->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Imię i nazwisko')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('phone')->label('Telefon')->toggleable(),
                Tables\Columns\TextColumn::make('hourly_rate_cents')
                    ->label('Stawka')
                    ->formatStateUsing(fn (?int $state) => $state !== null ? number_format($state / 100, 2, ',', ' ').' zł' : '—'),
                Tables\Columns\ColorColumn::make('color')->label('Kolor')->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->label('Aktywny')->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Status'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->after(self::auditCallback('instructor.update')),
                Tables\Actions\DeleteAction::make()->after(self::auditCallback('instructor.delete')),
                Tables\Actions\RestoreAction::make()->after(self::auditCallback('instructor.restore')),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInstructors::route('/'),
            'create' => Pages\CreateInstructor::route('/create'),
            'edit' => Pages\EditInstructor::route('/{record}/edit'),
        ];
    }

    private static function auditCallback(string $action): callable
    {
        return function (Model $record) use ($action) {
            app(TenantAuditLogger::class)->record($action, 'Instructor', (string) $record->getKey(), [
                'name' => $record->name,
            ]);
        };
    }
}
