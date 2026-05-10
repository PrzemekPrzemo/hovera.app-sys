<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\WebhookSubscriptionResource\Pages;
use App\Filament\Admin\Resources\WebhookSubscriptionResource\RelationManagers;
use App\Models\Central\Tenant;
use App\Models\Central\WebhookSubscription;
use App\Services\MasterAuditLogger;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Master-admin: cross-tenant view of outbound webhook subscriptions.
 * Tenants own the subscriptions but admin can audit them, force-disable
 * misbehaving ones, and inspect delivery history (relation manager).
 */
class WebhookSubscriptionResource extends Resource
{
    protected static ?string $model = WebhookSubscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?int $navigationSort = 60;

    public static function getNavigationLabel(): string
    {
        return __('admin/api-management.webhooks.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.configuration');
    }

    public static function getModelLabel(): string
    {
        return __('admin/api-management.webhooks.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/api-management.webhooks.model_plural');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user !== null && (bool) ($user->is_master_admin ?? false);
    }

    /** @return list<string> */
    public static function eventCatalog(): array
    {
        return [
            'invoice.paid',
            'invoice.created',
            'invoice.overdue',
            'booking.created',
            'booking.cancelled',
            'booking.updated',
            'horse.created',
            'horse.updated',
            'member.invited',
            'member.accepted',
            'subscription.updated',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('admin/api-management.webhooks.form.section.target'))
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('tenant_id')
                        ->label(__('admin/api-management.webhooks.form.tenant'))
                        ->options(fn () => Tenant::query()->orderBy('name')->pluck('name', 'id')->toArray())
                        ->searchable()
                        ->required(),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('admin/api-management.webhooks.form.is_active'))
                        ->default(true),
                    Forms\Components\TextInput::make('url')
                        ->label(__('admin/api-management.webhooks.form.url'))
                        ->required()
                        ->url()
                        ->maxLength(500)
                        ->columnSpanFull()
                        ->helperText(__('admin/api-management.webhooks.form.url_help')),
                    Forms\Components\CheckboxList::make('events')
                        ->label(__('admin/api-management.webhooks.form.events'))
                        ->options(array_combine(self::eventCatalog(), self::eventCatalog()))
                        ->columns(3)
                        ->required()
                        ->columnSpanFull(),
                ]),
            Forms\Components\Section::make(__('admin/api-management.webhooks.form.section.signing'))
                ->description(__('admin/api-management.webhooks.form.signing_help'))
                ->schema([
                    Forms\Components\TextInput::make('secret')
                        ->label(__('admin/api-management.webhooks.form.secret'))
                        ->required()
                        ->password()
                        ->revealable()
                        ->maxLength(64)
                        ->default(fn () => WebhookSubscription::generateSecret())
                        ->suffixAction(
                            Forms\Components\Actions\Action::make('regenerate')
                                ->icon('heroicon-o-arrow-path')
                                ->action(function (Forms\Set $set): void {
                                    $set('secret', WebhookSubscription::generateSecret());
                                    Notification::make()
                                        ->success()
                                        ->title(__('admin/api-management.webhooks.form.secret_regenerated'))
                                        ->send();
                                }),
                        ),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label(__('admin/api-management.webhooks.col.tenant'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('url_host')
                    ->label(__('admin/api-management.webhooks.col.url_host'))
                    ->getStateUsing(fn (WebhookSubscription $r) => parse_url($r->url, PHP_URL_HOST) ?: $r->url),
                Tables\Columns\TextColumn::make('events_count')
                    ->label(__('admin/api-management.webhooks.col.events'))
                    ->getStateUsing(fn (WebhookSubscription $r) => count((array) $r->events))
                    ->badge(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('admin/api-management.webhooks.col.is_active'))
                    ->boolean(),
                Tables\Columns\BadgeColumn::make('last_delivery_status')
                    ->label(__('admin/api-management.webhooks.col.last_delivery'))
                    ->colors([
                        'success' => 'success',
                        'warning' => 'client_error',
                        'danger' => 'failed',
                        'gray' => fn ($state) => $state === null,
                    ])
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('last_delivery_at')
                    ->label(__('admin/api-management.webhooks.col.last_delivery_at'))
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin/api-management.webhooks.col.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label(__('admin/api-management.webhooks.filter.tenant'))
                    ->relationship('tenant', 'name')
                    ->searchable(),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('admin/api-management.webhooks.filter.is_active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn (WebhookSubscription $r) => $r->is_active
                        ? __('admin/api-management.webhooks.action.disable')
                        : __('admin/api-management.webhooks.action.enable'))
                    ->icon('heroicon-o-power')
                    ->requiresConfirmation()
                    ->action(function (WebhookSubscription $record, MasterAuditLogger $audit): void {
                        $record->forceFill(['is_active' => ! $record->is_active])->save();
                        $audit->record('webhook.toggle_active', 'WebhookSubscription', $record->id, $record->tenant_id, [
                            'is_active' => $record->is_active,
                        ]);
                        Notification::make()->success()
                            ->title(__('admin/api-management.webhooks.action.toggled'))
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->after(function (WebhookSubscription $record, MasterAuditLogger $audit) {
                        $audit->record('webhook.deleted', 'WebhookSubscription', $record->id, $record->tenant_id, [
                            'url' => $record->url,
                        ]);
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DeliveriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWebhookSubscriptions::route('/'),
            'create' => Pages\CreateWebhookSubscription::route('/create'),
            'edit' => Pages\EditWebhookSubscription::route('/{record}/edit'),
        ];
    }
}
