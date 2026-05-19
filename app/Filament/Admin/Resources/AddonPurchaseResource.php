<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\AddonPurchaseResource\Pages;
use App\Models\Central\AddonPurchase;
use App\Models\Central\PlanAddon;
use App\Services\Billing\Przelewy24Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Master-admin: zarządzanie zakupami add-onów (one-time + recurring) dla
 * stajni transportowych. Patrz docs/TRANSPORT.md §13.
 *
 * Flow:
 *   1. Admin tworzy purchase: tenant + plan_addon → snapshot wartości,
 *      status=pending.
 *   2. Akcja "Wyślij link P24" → Przelewy24Service::chargeAddon —
 *      generuje sesję, zapisuje URL + p24_session_id.
 *   3. Tenant dostaje URL (mailem / przez panel admina handoff).
 *   4. Webhook P24 (`webhooks.p24.addon`) flipuje status=paid.
 *
 * Hovera jest tu merchant of record — pieniądze idą na nasze konto P24
 * (`services.przelewy24.*`). To są płatności ZA NAS (za nasze usługi
 * add-on), więc faktura wystawiana jest normalnie z naszego NIP'u.
 */
class AddonPurchaseResource extends Resource
{
    protected static ?string $model = AddonPurchase::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?int $navigationSort = 55;

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->is_master_admin;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin/addon_purchases.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.finances');
    }

    public static function getModelLabel(): string
    {
        return __('admin/addon_purchases.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/addon_purchases.model_plural');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('admin/addon_purchases.form.section.basics'))
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('tenant_id')
                        ->label(__('admin/addon_purchases.form.label.tenant'))
                        ->relationship('tenant', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\Select::make('plan_addon_id')
                        ->label(__('admin/addon_purchases.form.label.addon'))
                        ->options(fn () => PlanAddon::query()
                            ->where('is_global', true)
                            ->where('is_active', true)
                            ->orderBy('sort_order')
                            ->get()
                            ->mapWithKeys(fn (PlanAddon $a) => [$a->id => $a->code.' — '.$a->name])
                            ->all())
                        ->searchable()
                        ->required()
                        ->live()
                        // Auto-populate snapshot z PlanAddon przy wyborze.
                        // Trzymamy wartości na purchase żeby cennik mógł
                        // się zmienić nie ruszając historii.
                        ->afterStateUpdated(function (?string $state, Forms\Set $set) {
                            if (! $state) {
                                return;
                            }
                            $addon = PlanAddon::query()->find($state);
                            if (! $addon) {
                                return;
                            }
                            $set('addon_code', $addon->code);
                            $set('addon_name', $addon->name);
                            $set('currency', $addon->currency ?? 'PLN');
                            $set('amount_cents', $addon->priceFor($addon->currency ?? 'PLN'));
                        }),

                    Forms\Components\TextInput::make('addon_code')
                        ->label(__('admin/addon_purchases.form.label.addon_code'))
                        ->required()
                        ->maxLength(64),
                    Forms\Components\TextInput::make('addon_name')
                        ->label(__('admin/addon_purchases.form.label.addon_name'))
                        ->required()
                        ->maxLength(200),

                    Forms\Components\Select::make('currency')
                        ->label(__('admin/addon_purchases.form.label.currency'))
                        ->options(['PLN' => 'PLN', 'EUR' => 'EUR', 'GBP' => 'GBP'])
                        ->default('PLN')
                        ->required(),
                    Forms\Components\TextInput::make('amount_cents')
                        ->label(__('admin/addon_purchases.form.label.amount_cents'))
                        ->helperText(__('admin/addon_purchases.form.helper.amount_cents'))
                        ->required()
                        ->numeric()
                        ->minValue(1),
                ]),
            Forms\Components\Section::make(__('admin/addon_purchases.form.section.status'))
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label(__('admin/addon_purchases.form.label.status'))
                        ->options([
                            AddonPurchase::STATUS_PENDING => __('admin/addon_purchases.status.pending'),
                            AddonPurchase::STATUS_PAID => __('admin/addon_purchases.status.paid'),
                            AddonPurchase::STATUS_FAILED => __('admin/addon_purchases.status.failed'),
                            AddonPurchase::STATUS_CANCELLED => __('admin/addon_purchases.status.cancelled'),
                        ])
                        ->default(AddonPurchase::STATUS_PENDING)
                        ->required(),
                    Forms\Components\Placeholder::make('p24_payment_url_preview')
                        ->label(__('admin/addon_purchases.form.label.p24_link'))
                        ->content(fn ($record) => $record?->p24_payment_url ?: __('admin/addon_purchases.form.label.p24_link_none')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label(__('admin/addon_purchases.table.column.tenant'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('addon_code')
                    ->label(__('admin/addon_purchases.table.column.addon'))
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount_cents')
                    ->label(__('admin/addon_purchases.table.column.amount'))
                    ->formatStateUsing(fn (AddonPurchase $record) => $record->amountFormatted()),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin/addon_purchases.table.column.status'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        AddonPurchase::STATUS_PAID => 'success',
                        AddonPurchase::STATUS_PENDING => 'warning',
                        AddonPurchase::STATUS_FAILED, AddonPurchase::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label(__('admin/addon_purchases.table.column.paid_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin/addon_purchases.table.column.created_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('generate_p24_link')
                    ->label(__('admin/addon_purchases.action.generate_p24_link'))
                    ->icon('heroicon-o-credit-card')
                    ->color('primary')
                    ->visible(fn (AddonPurchase $record) => ! $record->isTerminal())
                    ->action(function (AddonPurchase $record) {
                        try {
                            $url = app(Przelewy24Service::class)->chargeAddon($record);

                            Notification::make()
                                ->success()
                                ->title(__('admin/addon_purchases.notify.link_generated'))
                                ->body($url)
                                ->persistent()
                                ->send();
                        } catch (\Throwable $e) {
                            Log::error('Addon P24 link generation failed', [
                                'purchase_id' => $record->id,
                                'error' => $e->getMessage(),
                            ]);
                            Notification::make()
                                ->danger()
                                ->title(__('admin/addon_purchases.notify.link_failed'))
                                ->body($e->getMessage())
                                ->persistent()
                                ->send();
                        }
                    }),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAddonPurchases::route('/'),
            'create' => Pages\CreateAddonPurchase::route('/create'),
            'edit' => Pages\EditAddonPurchase::route('/{record}/edit'),
        ];
    }
}
