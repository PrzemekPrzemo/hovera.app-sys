<?php

declare(strict_types=1);

namespace App\Filament\Owner\Resources;

use App\Domain\Horses\HorseOwnerStableAccessGate;
use App\Domain\Horses\HorseRegistrySyncService;
use App\Filament\Owner\Resources\HorseResource\Pages;
use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Tenant\OwnerHorse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

/**
 * Owner: "Moje konie". Lekki CRUD z minimum pól — owner nie zarządza
 * boksami, vetem, pensjonem (to stable). Trzyma tylko własną kartotekę
 * koni: imię, rasa, data ur., paszport, microchip, notatki.
 *
 * Patrz docs/MARKETPLACE-ROADMAP.md PR 6 §"Schema".
 */
class HorseResource extends Resource
{
    protected static ?string $model = OwnerHorse::class;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.owner_horses');
    }

    public static function getNavigationLabel(): string
    {
        return __('owner/horses.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('owner/horses.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('owner/horses.model.plural');
    }

    /** @return array<string,string> */
    public static function sexOptions(): array
    {
        return [
            'mare' => __('owner/horses.sex.mare'),
            'stallion' => __('owner/horses.sex.stallion'),
            'gelding' => __('owner/horses.sex.gelding'),
            'filly' => __('owner/horses.sex.filly'),
            'colt' => __('owner/horses.sex.colt'),
            'foal' => __('owner/horses.sex.foal'),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('owner/horses.form.section.identification'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('owner/horses.form.label.name'))
                        ->required()
                        ->maxLength(120),
                    Forms\Components\TextInput::make('breed')
                        ->label(__('owner/horses.form.label.breed'))
                        ->maxLength(120),
                    Forms\Components\DatePicker::make('birth_date')
                        ->label(__('owner/horses.form.label.birth_date'))
                        ->native(false)
                        ->maxDate(now()),
                    Forms\Components\Select::make('sex')
                        ->label(__('owner/horses.form.label.sex'))
                        ->options(self::sexOptions())
                        ->native(false),
                    Forms\Components\TextInput::make('color')
                        ->label(__('owner/horses.form.label.color'))
                        ->maxLength(60),
                    Forms\Components\TextInput::make('passport_number')
                        ->label(__('owner/horses.form.label.passport_number'))
                        ->maxLength(64),
                    Forms\Components\TextInput::make('microchip')
                        ->label(__('owner/horses.form.label.microchip'))
                        ->maxLength(32),
                ]),

            Forms\Components\Section::make(__('owner/horses.form.section.notes'))
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->label(__('owner/horses.form.label.notes'))
                        ->rows(4)
                        ->maxLength(2000),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('owner/horses.table.name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('breed')
                    ->label(__('owner/horses.table.breed'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('birth_date')
                    ->label(__('owner/horses.table.birth_date'))
                    ->date()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('sex')
                    ->label(__('owner/horses.table.sex'))
                    ->formatStateUsing(fn (?string $state) => $state === null ? '—' : (self::sexOptions()[$state] ?? $state))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('passport_number')
                    ->label(__('owner/horses.table.passport_number'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                self::viewBoardingDetailsAction(),
                Tables\Actions\EditAction::make(),
                self::connectToStableAction(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->emptyStateHeading(__('owner/horses.empty.heading'))
            ->emptyStateDescription(__('owner/horses.empty.description'))
            ->emptyStateIcon('heroicon-o-bolt');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHorses::route('/'),
            'create' => Pages\CreateHorse::route('/create'),
            'edit' => Pages\EditHorse::route('/{record}/edit'),
        ];
    }

    /**
     * "Szczegóły boardingu" — link do HorseDetail page'a (Faza 1 Owner ↔
     * Stable view). Visible tylko gdy koń ma central_horse_id (registry
     * sync zrobiony) i Auth user ma active boarding assignment dla tego
     * konia. Pełny dostęp (timeline, photos, documents, care, messages)
     * jest dalej dostępny przez podstronu z details'a.
     */
    private static function viewBoardingDetailsAction(): Action
    {
        return Action::make('view_boarding_details')
            ->label(__('owner/horses.action.view_details.label'))
            ->icon('heroicon-o-eye')
            ->color('info')
            ->visible(function (OwnerHorse $record) {
                if ($record->central_horse_id === null) {
                    return false;
                }
                $user = Auth::user();
                if (! $user instanceof User) {
                    return false;
                }

                return app(HorseOwnerStableAccessGate::class)
                    ->tryAuthorize($user, $record->central_horse_id) !== null;
            })
            ->url(fn (OwnerHorse $record) => url('/owner/horses/'.$record->central_horse_id.'/details'));
    }

    /**
     * "Połącz ze stajnią" — owner składa request boarding'u dla danego
     * konia. Wymaga `central_horse_id` na OwnerHorse (Horse zsynchowany
     * z central registry). Stajnia później widzi pending request w
     * `/app/pending-boarding-requests` i klika "Akceptuj".
     *
     * Visible tylko gdy koń ma central_horse_id (sync zrobiony przy
     * Create) i brak istniejacego pending/active assignment dla tego
     * konia w którejkolwiek stajni (idempotent na poziomie serwisu).
     */
    private static function connectToStableAction(): Action
    {
        return Action::make('connect_to_stable')
            ->label(__('owner/horses.action.connect.label'))
            ->icon('heroicon-o-building-storefront')
            ->color('primary')
            ->visible(fn (OwnerHorse $record) => $record->central_horse_id !== null)
            ->form([
                Forms\Components\Select::make('stable_tenant_id')
                    ->label(__('owner/horses.action.connect.stable_label'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->options(fn () => Tenant::query()
                        ->stables()
                        ->whereIn('status', Tenant::PANEL_ACCESSIBLE_STATUSES)
                        ->orderBy('name')
                        ->limit(50)
                        ->pluck('name', 'id')
                        ->all())
                    ->getOptionLabelUsing(fn ($value) => Tenant::query()->find($value)?->name)
                    ->helperText(__('owner/horses.action.connect.stable_helper')),
            ])
            ->modalHeading(fn (OwnerHorse $record) => __('owner/horses.action.connect.modal_heading', [
                'horse' => $record->name,
            ]))
            ->modalDescription(__('owner/horses.action.connect.modal_description'))
            ->action(function (OwnerHorse $record, array $data) {
                $stable = Tenant::find($data['stable_tenant_id']);
                if (! $stable || ! $stable->isStable()) {
                    Notification::make()->danger()
                        ->title(__('owner/horses.action.connect.notify_invalid_stable'))
                        ->send();

                    return;
                }

                /** @var CentralHorseRegistry|null $centralHorse */
                $centralHorse = CentralHorseRegistry::find($record->central_horse_id);
                if ($centralHorse === null) {
                    Notification::make()->danger()
                        ->title(__('owner/horses.action.connect.notify_no_central'))
                        ->send();

                    return;
                }

                $owner = Auth::user();
                $assignment = app(HorseRegistrySyncService::class)
                    ->requestBoarding($centralHorse, $stable, $owner instanceof User ? $owner : null);

                $isAlreadyActive = $assignment->status === HorseBoardingAssignment::STATUS_ACTIVE;
                Notification::make()
                    ->success()
                    ->title($isAlreadyActive
                        ? __('owner/horses.action.connect.notify_already_active_title')
                        : __('owner/horses.action.connect.notify_requested_title'))
                    ->body($isAlreadyActive
                        ? __('owner/horses.action.connect.notify_already_active_body', ['stable' => $stable->name])
                        : __('owner/horses.action.connect.notify_requested_body', ['stable' => $stable->name]))
                    ->send();
            });
    }
}
