<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MasterAdResource\Pages;
use App\Models\Central\MasterAd;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class MasterAdResource extends Resource
{
    protected static ?string $model = MasterAd::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?int $navigationSort = 8;

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->is_master_admin;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin/master_ads.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.configuration');
    }

    public static function getModelLabel(): string
    {
        return __('admin/master_ads.model.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/master_ads.model.plural');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('admin/master_ads.section.content'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label(__('admin/master_ads.field.title'))
                        ->required()
                        ->maxLength(200)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('body')
                        ->label(__('admin/master_ads.field.body'))
                        ->required()
                        ->rows(4)
                        ->maxLength(2000)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('cta_label')
                        ->label(__('admin/master_ads.field.cta_label'))
                        ->maxLength(80),
                    Forms\Components\TextInput::make('cta_url')
                        ->label(__('admin/master_ads.field.cta_url'))
                        ->url()
                        ->maxLength(500),
                    Forms\Components\Radio::make('placement')
                        ->label(__('admin/master_ads.field.placement'))
                        ->options([
                            'banner' => __('admin/master_ads.placement.banner'),
                            'modal' => __('admin/master_ads.placement.modal'),
                        ])
                        ->default('banner')
                        ->inline()
                        ->required(),
                    Forms\Components\Radio::make('variant')
                        ->label(__('admin/master_ads.field.variant'))
                        ->options([
                            'info' => __('admin/master_ads.variant.info'),
                            'promo' => __('admin/master_ads.variant.promo'),
                            'warning' => __('admin/master_ads.variant.warning'),
                        ])
                        ->default('info')
                        ->inline()
                        ->required(),
                ]),

            Forms\Components\Section::make(__('admin/master_ads.section.schedule'))
                ->columns(3)
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('admin/master_ads.field.is_active'))
                        ->default(true)
                        ->inline(false),
                    Forms\Components\DateTimePicker::make('starts_at')
                        ->label(__('admin/master_ads.field.starts_at'))
                        ->helperText(__('admin/master_ads.field.starts_at_help')),
                    Forms\Components\DateTimePicker::make('ends_at')
                        ->label(__('admin/master_ads.field.ends_at'))
                        ->helperText(__('admin/master_ads.field.ends_at_help')),
                ]),

            Forms\Components\Section::make(__('admin/master_ads.section.targeting'))
                ->description(__('admin/master_ads.section.targeting_help'))
                ->schema([
                    Forms\Components\Select::make('targeting.roles')
                        ->label(__('admin/master_ads.field.targeting_roles'))
                        ->multiple()
                        ->options([
                            'owner' => 'Owner',
                            'admin' => 'Admin',
                            'manager' => 'Manager',
                            'instructor' => __('admin/master_ads.role.instructor'),
                            'employee' => __('admin/master_ads.role.employee'),
                            'vet' => __('admin/master_ads.role.vet'),
                            'viewer' => __('admin/master_ads.role.viewer'),
                        ])
                        ->helperText(__('admin/master_ads.field.targeting_roles_help')),
                    Forms\Components\Select::make('targeting.tenant_ids')
                        ->label(__('admin/master_ads.field.targeting_tenants'))
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(fn () => Tenant::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->helperText(__('admin/master_ads.field.targeting_tenants_help')),
                    Forms\Components\TagsInput::make('targeting.countries')
                        ->label(__('admin/master_ads.field.targeting_countries'))
                        ->placeholder('PL, DE, FR')
                        ->helperText(__('admin/master_ads.field.targeting_countries_help')),
                    Forms\Components\Select::make('targeting.locales')
                        ->label(__('admin/master_ads.field.targeting_locales'))
                        ->multiple()
                        ->options([
                            'pl' => 'Polski',
                            'en' => 'English',
                            'fr' => 'Français',
                            'de' => 'Deutsch',
                            'ru' => 'Русский',
                        ])
                        ->helperText(__('admin/master_ads.field.targeting_locales_help')),
                    Forms\Components\Select::make('targeting.user_ids')
                        ->label(__('admin/master_ads.field.targeting_users'))
                        ->multiple()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search) => User::query()
                            ->where(fn ($q) => $q->where('email', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                            ->limit(20)
                            ->get()
                            ->mapWithKeys(fn ($u) => [$u->id => $u->name.' ('.$u->email.')'])
                            ->all())
                        ->getOptionLabelsUsing(fn (array $values) => User::query()
                            ->whereIn('id', $values)
                            ->get()
                            ->mapWithKeys(fn ($u) => [$u->id => $u->name.' ('.$u->email.')'])
                            ->all())
                        ->helperText(__('admin/master_ads.field.targeting_users_help')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->sortable()->limit(60),
                Tables\Columns\TextColumn::make('placement')
                    ->badge()
                    ->formatStateUsing(fn (string $s) => __('admin/master_ads.placement.'.$s)),
                Tables\Columns\TextColumn::make('variant')
                    ->badge()
                    ->color(fn (string $s): string => match ($s) {
                        'promo' => 'success',
                        'warning' => 'warning',
                        default => 'info',
                    }),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label(__('admin/master_ads.field.is_active_short')),
                Tables\Columns\TextColumn::make('starts_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('ends_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('impressions_count')->label(__('admin/master_ads.field.impressions'))->numeric(),
                Tables\Columns\TextColumn::make('clicks_count')->label(__('admin/master_ads.field.clicks'))->numeric(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->mutateFormDataUsing(fn (array $data) => self::stampCreator($data));
    }

    private static function stampCreator(array $data): array
    {
        $data['created_by'] ??= Auth::id();

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMasterAds::route('/'),
            'create' => Pages\CreateMasterAd::route('/create'),
            'edit' => Pages\EditMasterAd::route('/{record}/edit'),
        ];
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'title';
    }

    public static function getRecord(string $key): ?Model
    {
        return MasterAd::query()->find($key);
    }
}
