<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\InternalChannelResource\Pages;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Models\Tenant\InternalChannel;
use App\Services\Tenancy\TenantRoleGate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Channel C w panelu stajni (PR O5 epic 2 UI) — kanały komunikacji
 * wewnętrznej (#general/#weterynaria/#transport auto + admin może dodać).
 *
 * Kanały żyją w tenant DB — standardowy resource (bez central scoping).
 */
class InternalChannelResource extends Resource
{
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::HORSE_AND_CARE_STAFF;
    }

    protected static ?string $model = InternalChannel::class;

    protected static ?string $navigationIcon = 'heroicon-o-hashtag';

    protected static ?int $navigationSort = 28;

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.stable');
    }

    public static function getNavigationLabel(): string
    {
        return __('app/internal_channel.nav');
    }

    public static function getModelLabel(): string
    {
        return __('app/internal_channel.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app/internal_channel.model_plural');
    }

    public static function canCreate(): bool
    {
        // Nowe kanały dodają tylko admini/managerowie (per captured decisions §4).
        return app(TenantRoleGate::class)->allows(TenantRoleGate::FULL_ADMINS_AND_MANAGERS);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label(__('app/internal_channel.form.name'))
                ->required()
                ->maxLength(120),
            Forms\Components\TextInput::make('description')
                ->label(__('app/internal_channel.form.description'))
                ->maxLength(500),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('app/internal_channel.table.name'))
                    ->formatStateUsing(fn (string $state) => '#'.$state)
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('description')
                    ->label(__('app/internal_channel.table.description'))
                    ->limit(60)
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_default')
                    ->label(__('app/internal_channel.table.default'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('members_count')
                    ->label(__('app/internal_channel.table.members'))
                    ->counts('members'),
            ])
            ->defaultSort('is_default', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make()->label(__('app/internal_channel.action.open')),
                Tables\Actions\DeleteAction::make()
                    // Domyślne kanały są nieusuwalne.
                    ->visible(fn (InternalChannel $r) => ! $r->is_default),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInternalChannels::route('/'),
            'create' => Pages\CreateInternalChannel::route('/create'),
            'view' => Pages\ViewInternalChannel::route('/{record}'),
        ];
    }
}
