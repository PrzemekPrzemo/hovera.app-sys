<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\HorseResource\Pages;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Models\Tenant\BoardingService;
use App\Models\Tenant\Box;
use App\Models\Tenant\Client;
use App\Models\Tenant\Horse;
use App\Services\Integrations\LiveJumping\LiveJumpingClient;
use App\Services\Integrations\LiveJumping\LiveJumpingFeatureGate;
use App\Services\Tenancy\TenantRoleGate;
use App\Services\TenantAuditLogger;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class HorseResource extends Resource
{
    use RestrictedByTenantRole;

    protected static ?string $model = Horse::class;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::HORSE_AND_CARE_STAFF;
    }

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.stable');
    }

    public static function getNavigationLabel(): string
    {
        return __('navigation.horses');
    }

    public static function getModelLabel(): string
    {
        return __('models.horse');
    }

    public static function getPluralModelLabel(): string
    {
        return __('models.horses');
    }

    protected static ?int $navigationSort = 10;

    /** @return array<string,string> */
    public static function sexOptions(): array
    {
        return [
            'mare' => __('app/horse.sex.mare'),
            'gelding' => __('app/horse.sex.gelding'),
            'stallion' => __('app/horse.sex.stallion'),
            'breeding_stallion' => __('app/horse.sex.breeding_stallion'),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('app/horse.form.section.identification'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('app/horse.form.label.name'))
                        ->required()->maxLength(120),
                    Forms\Components\Select::make('owner_client_id')
                        ->label(__('app/horse.form.label.owner'))
                        ->options(fn () => Client::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->placeholder(__('app/horse.form.label.owner_placeholder')),
                    Forms\Components\Select::make('box_id')
                        ->label(__('app/horse.form.label.box'))
                        ->options(fn () => Box::query()
                            ->where('is_active', true)
                            ->orderBy('sort_order')
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->placeholder(__('app/horse.form.label.box_placeholder'))
                        ->helperText(__('app/horse.form.helper.box')),
                    Forms\Components\TextInput::make('microchip')
                        ->label(__('app/horse.form.label.microchip'))
                        ->maxLength(32),
                    Forms\Components\TextInput::make('passport_number')
                        ->label(__('app/horse.form.label.passport_number'))
                        ->maxLength(64),
                    Forms\Components\TextInput::make('ueln')
                        ->label(__('app/horse.form.label.ueln'))
                        ->maxLength(15)
                        ->helperText(__('app/horse.form.helper.ueln')),
                ]),

            Forms\Components\Section::make(__('app/horse.form.section.characteristics'))
                ->columns(4)
                ->schema([
                    Forms\Components\Select::make('sex')
                        ->label(__('app/horse.form.label.sex'))
                        ->options(self::sexOptions()),
                    Forms\Components\TextInput::make('breed')
                        ->label(__('app/horse.form.label.breed'))
                        ->maxLength(120),
                    Forms\Components\TextInput::make('color')
                        ->label(__('app/horse.form.label.color'))
                        ->maxLength(60),
                    Forms\Components\DatePicker::make('birth_date')
                        ->label(__('app/horse.form.label.birth_date')),
                ]),

            Forms\Components\Section::make(__('app/horse.form.section.boarding'))
                ->description(__('app/horse.form.section.boarding_description'))
                ->collapsed()
                ->schema([
                    Forms\Components\Select::make('boardingServices')
                        ->label(__('app/horse.form.label.boarding_services'))
                        ->multiple()
                        ->relationship('boardingServices', 'name')
                        ->options(fn () => BoardingService::query()
                            ->where('is_active', true)
                            ->orderBy('sort_order')
                            ->pluck('name', 'id'))
                        ->preload()
                        ->searchable()
                        ->helperText(__('app/horse.form.helper.boarding_services')),
                ]),

            Forms\Components\Section::make(__('app/horse.form.section.notes'))
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('notes')->rows(4),
                ]),

            // LiveJumping integration — widoczna TYLKO gdy master admin
            // włączył partnership w /admin/live-jumping-settings. Operator
            // stajni wkleja URL profilu konia z livejumping.com, hovera
            // pobiera palmares + nadchodzące starty.
            Forms\Components\Section::make(__('app/horse.form.section.sport'))
                ->description(__('app/horse.form.section.sport_help'))
                ->collapsed()
                ->icon('heroicon-o-trophy')
                ->visible(fn () => app(LiveJumpingFeatureGate::class)->enabled())
                ->schema([
                    Forms\Components\TextInput::make('livejumping_profile_url')
                        ->label(__('app/horse.form.label.livejumping_profile_url'))
                        ->helperText(__('app/horse.form.helper.livejumping_profile_url'))
                        ->url()
                        ->maxLength(500)
                        ->placeholder('https://livejumping.com/horse/...'),
                    Forms\Components\Placeholder::make('livejumping_palmares')
                        ->label(__('app/horse.form.label.livejumping_palmares'))
                        ->content(function (Get $get): HtmlString {
                            $url = (string) $get('livejumping_profile_url');
                            if ($url === '') {
                                return new HtmlString(
                                    '<span class="text-gray-500 text-sm">'.e(__('app/horse.form.helper.livejumping_no_profile')).'</span>'
                                );
                            }
                            $profile = app(LiveJumpingClient::class)
                                ->getHorseProfile($url);
                            if ($profile === null) {
                                return new HtmlString(
                                    '<span class="text-amber-600 text-sm">'.e(__('app/horse.form.helper.livejumping_fetch_failed')).'</span>'
                                );
                            }

                            return self::renderHorsePalmares($profile);
                        }),
                ]),
        ]);
    }

    /**
     * Renderuje palmares konia jako HTML — stats + recent results.
     * Wyciągnięte do osobnej metody, żeby Placeholder content był
     * kompaktowy i łatwo testowalny.
     */
    private static function renderHorsePalmares(array $profile): HtmlString
    {
        $stats = (array) ($profile['stats'] ?? []);
        $recent = (array) ($profile['recent_results'] ?? []);

        $html = '<div class="space-y-3 text-sm">';
        $html .= '<div class="grid grid-cols-2 sm:grid-cols-4 gap-2">';
        foreach (['starts', 'wins', 'placings', 'ranking_points'] as $statKey) {
            if (! array_key_exists($statKey, $stats)) {
                continue;
            }
            $label = __('app/horse.form.stats.'.$statKey);
            $value = e((string) $stats[$statKey]);
            $html .= '<div class="rounded-lg border border-gray-200 dark:border-gray-700 p-2">';
            $html .= '<div class="text-xs text-gray-500">'.e($label).'</div>';
            $html .= '<div class="text-lg font-semibold text-gray-900 dark:text-gray-100">'.$value.'</div>';
            $html .= '</div>';
        }
        $html .= '</div>';

        if ($recent !== []) {
            $html .= '<div>';
            $html .= '<div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">';
            $html .= e(__('app/horse.form.stats.recent_results'));
            $html .= '</div>';
            $html .= '<ul class="space-y-1">';
            foreach (array_slice($recent, 0, 10) as $r) {
                $r = (array) $r;
                $when = e((string) ($r['date'] ?? ''));
                $name = e((string) ($r['competition_name'] ?? ''));
                $class = e((string) ($r['class'] ?? ''));
                $rank = e((string) ($r['rank'] ?? '—'));
                $html .= '<li class="flex justify-between gap-3 text-xs">';
                $html .= '<span class="text-gray-500">'.$when.'</span>';
                $html .= '<span class="flex-1 truncate text-gray-900 dark:text-gray-100">'.$name.' · '.$class.'</span>';
                $html .= '<span class="font-semibold">'.$rank.'</span>';
                $html .= '</li>';
            }
            $html .= '</ul></div>';
        }
        $html .= '</div>';

        return new HtmlString($html);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('app/horse.table.column.name'))
                    ->searchable()->sortable(),
                Tables\Columns\TextColumn::make('breed')
                    ->label(__('app/horse.table.column.breed'))
                    ->toggleable()->searchable(),
                Tables\Columns\BadgeColumn::make('sex')
                    ->label(__('app/horse.table.column.sex'))
                    ->formatStateUsing(fn (?string $state) => $state === null ? '—' : (self::sexOptions()[$state] ?? $state)),
                Tables\Columns\TextColumn::make('color')
                    ->label(__('app/horse.table.column.color'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('birth_date')
                    ->label(__('app/horse.table.column.birth_date'))
                    ->date()->sortable(),
                Tables\Columns\TextColumn::make('owner.name')
                    ->label(__('app/horse.table.column.owner'))
                    ->placeholder(__('app/horse.table.column.owner_placeholder'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('microchip')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('app/horse.table.column.created_at'))
                    ->date()->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('sex')->options(self::sexOptions()),
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
            HorseResource\RelationManagers\WeightMeasurementsRelationManager::class,
            HorseResource\RelationManagers\FeedingPlanRelationManager::class,
            HorseResource\RelationManagers\ActivitiesRelationManager::class,
            HorseResource\RelationManagers\MessagesRelationManager::class,
            HorseResource\RelationManagers\DocumentsRelationManager::class,
            HorseResource\RelationManagers\PhotosRelationManager::class,
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
