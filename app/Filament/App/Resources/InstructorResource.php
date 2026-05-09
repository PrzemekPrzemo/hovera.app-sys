<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\InstructorResource\Pages;
use App\Filament\Components\PriceInput;
use App\Models\Tenant\Instructor;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
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

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.calendar');
    }

    public static function getNavigationLabel(): string
    {
        return __('navigation.instructors');
    }

    public static function getModelLabel(): string
    {
        return __('models.instructor');
    }

    public static function getPluralModelLabel(): string
    {
        return __('models.instructors');
    }

    protected static ?int $navigationSort = 60;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('app/instructor.form.section.data'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('app/instructor.form.label.name'))->required()->maxLength(255),
                    Forms\Components\TextInput::make('email')->email()->maxLength(255),
                    Forms\Components\TextInput::make('phone')
                        ->label(__('app/instructor.form.label.phone'))->tel()->maxLength(40),
                    PriceInput::make('hourly_rate_cents', __('app/instructor.form.label.hourly_rate')),
                    Forms\Components\ColorPicker::make('color')
                        ->label(__('app/instructor.form.label.color')),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('app/instructor.form.label.is_active'))->default(true),
                ]),
            Forms\Components\Textarea::make('notes')
                ->label(__('app/instructor.form.label.notes'))->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('app/instructor.table.column.name'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label(__('app/instructor.table.column.phone'))->toggleable(),
                Tables\Columns\TextColumn::make('hourly_rate_cents')
                    ->label(__('app/instructor.table.column.hourly_rate'))
                    ->formatStateUsing(fn (?int $state) => $state !== null ? number_format($state / 100, 2, ',', ' ').' zł' : '—'),
                Tables\Columns\ColorColumn::make('color')
                    ->label(__('app/instructor.table.column.color'))->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('app/instructor.table.column.is_active'))->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('app/instructor.table.filter.status')),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('ics_url')
                    ->label(__('app/instructor.actions.ics_url'))
                    ->icon('heroicon-o-calendar-days')
                    ->color('gray')
                    ->modalHeading(fn (Instructor $r) => __('app/instructor.ics_modal.heading', ['name' => $r->name]))
                    ->modalDescription(__('app/instructor.ics_modal.description'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('app/instructor.ics_modal.close'))
                    ->form(fn (Instructor $r) => [
                        Forms\Components\TextInput::make('url')
                            ->label(__('app/instructor.ics_modal.url_label'))
                            ->default(self::icsUrlFor($r))
                            ->readOnly()
                            ->extraAttributes(['onclick' => 'this.select();']),
                        Forms\Components\Placeholder::make('howto')
                            ->label('')
                            ->content(__('app/instructor.ics_modal.howto')),
                    ])
                    ->action(function (Instructor $r) {
                        // Sole purpose of action callback: lazy-create token if missing
                        $r->ensureIcsToken();
                        Notification::make()
                            ->title(__('app/instructor.ics_modal.token_ensured'))
                            ->success()
                            ->send();
                    }),
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

    private static function icsUrlFor(Instructor $instructor): string
    {
        $tenant = app(TenantManager::class)->current();
        $token = $instructor->ensureIcsToken();

        if (! $tenant) {
            return '';
        }

        return route('public.instructor_calendar', [
            'slug' => $tenant->slug,
            'token' => $token,
        ]);
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
