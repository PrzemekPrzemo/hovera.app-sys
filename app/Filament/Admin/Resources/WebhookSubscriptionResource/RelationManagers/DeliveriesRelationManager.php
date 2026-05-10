<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\WebhookSubscriptionResource\RelationManagers;

use App\Jobs\Webhooks\DeliverWebhookJob;
use App\Models\Central\WebhookDelivery;
use App\Models\Central\WebhookSubscription;
use App\Services\MasterAuditLogger;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Last 50 delivery attempts for this subscription. Resend re-queues the
 * SAME payload via the SAME job, bumping attempt_number — useful when the
 * receiver has fixed a bug and wants to backfill missed events.
 */
class DeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveries';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin/api-management.webhooks.deliveries.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $q) => $q->latest('created_at')->limit(50))
            ->columns([
                Tables\Columns\TextColumn::make('event')
                    ->label(__('admin/api-management.webhooks.deliveries.col.event'))
                    ->badge(),
                Tables\Columns\TextColumn::make('attempt_number')
                    ->label(__('admin/api-management.webhooks.deliveries.col.attempt'))
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('status_code')
                    ->label(__('admin/api-management.webhooks.deliveries.col.status'))
                    ->badge()
                    ->placeholder('—')
                    ->color(function ($state): string {
                        if ($state === null) {
                            return 'gray';
                        }
                        if ($state >= 200 && $state < 300) {
                            return 'success';
                        }
                        if ($state >= 400 && $state < 500) {
                            return 'warning';
                        }

                        return 'danger';
                    }),
                Tables\Columns\TextColumn::make('duration_ms')
                    ->label(__('admin/api-management.webhooks.deliveries.col.duration'))
                    ->suffix(' ms'),
                Tables\Columns\TextColumn::make('delivered_at')
                    ->label(__('admin/api-management.webhooks.deliveries.col.delivered_at'))
                    ->dateTime(),
                Tables\Columns\TextColumn::make('error_message')
                    ->label(__('admin/api-management.webhooks.deliveries.col.error'))
                    ->placeholder('—')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->infolist([
                        TextEntry::make('event'),
                        TextEntry::make('status_code'),
                        TextEntry::make('attempt_number'),
                        TextEntry::make('duration_ms')->suffix(' ms'),
                        TextEntry::make('delivered_at')->dateTime(),
                        TextEntry::make('error_message')->columnSpanFull(),
                        TextEntry::make('payload')
                            ->label(__('admin/api-management.webhooks.deliveries.col.payload'))
                            ->columnSpanFull()
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : (string) $state)
                            ->fontFamily('mono'),
                        TextEntry::make('response_body')
                            ->columnSpanFull()
                            ->fontFamily('mono'),
                    ]),
                Tables\Actions\Action::make('resend')
                    ->label(__('admin/api-management.webhooks.deliveries.action.resend'))
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->action(function (WebhookDelivery $record, MasterAuditLogger $audit): void {
                        /** @var WebhookSubscription $subscription */
                        $subscription = $this->getOwnerRecord();
                        DeliverWebhookJob::dispatch(
                            $subscription->id,
                            $record->event,
                            (array) $record->payload,
                            $record->attempt_number + 1,
                        );

                        $audit->record('webhook.delivery_resent', 'WebhookDelivery', (string) $record->id, (string) $subscription->tenant_id, [
                            'event' => $record->event,
                            'original_attempt' => $record->attempt_number,
                        ]);

                        Notification::make()
                            ->success()
                            ->title(__('admin/api-management.webhooks.deliveries.action.resent'))
                            ->send();
                    }),
            ]);
    }
}
