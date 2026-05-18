<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TransportReviewResource\Pages;
use App\Models\Central\Tenant;
use App\Models\Central\TransportReview;
use App\Services\MasterAuditLogger;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Master admin moderacja recenzji marketplace'u — patrz docs/TRANSPORT.md §12.
 *
 * Hovera = pośrednik. Recenzje są publikowane raw przez klientów (real-deal
 * gate). Transporter może zgłosić review (status=flagged) — wtedy ląduje
 * tutaj do triage. Master admin może:
 *
 *   - Publish → wraca status=published (review znów widoczna publicznie)
 *   - Hide    → status=hidden (review znika, transporter widzi że to staff
 *               decision, nie może odflagować sam)
 *   - Reject  → soft delete (rzadko — gdy review oczywista pomyłka /
 *               podwojony submit; w praktyce flagged + hidden wystarcza)
 *
 * Każda akcja idzie do MasterAuditLogger.
 */
class TransportReviewResource extends Resource
{
    protected static ?string $model = TransportReview::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('admin/transport_reviews.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.transport_admin');
    }

    public static function getModelLabel(): string
    {
        return __('admin/transport_reviews.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/transport_reviews.model.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = TransportReview::query()->where('status', 'flagged')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNotNull('submitted_at');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transporter.name')
                    ->label(__('admin/transport_reviews.table.column.transporter'))
                    ->searchable(['transporter.name', 'transporter.slug'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('rating')
                    ->label(__('admin/transport_reviews.table.column.rating'))
                    ->formatStateUsing(fn ($state) => str_repeat('★', (int) $state).str_repeat('☆', max(0, 5 - (int) $state)))
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label(__('admin/transport_reviews.table.column.customer'))
                    ->formatStateUsing(fn ($state) => TransportReview::redactCustomerName($state))
                    ->searchable(),
                Tables\Columns\TextColumn::make('comment')
                    ->label(__('admin/transport_reviews.table.column.comment'))
                    ->limit(60)
                    ->wrap()
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin/transport_reviews.table.column.status'))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'published' => 'success',
                        'flagged' => 'warning',
                        'hidden' => 'gray',
                        default => 'info',
                    })
                    ->formatStateUsing(fn ($state) => __('transport/reviews.status.'.$state)),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('flagged_by_tenant_at')
                    ->label(__('admin/transport_reviews.table.column.flagged_at'))
                    ->dateTime()
                    ->toggleable(),
            ])
            // Flagged najpierw — wymagają decyzji staff'a.
            ->defaultSort(fn (Builder $q) => $q->orderByRaw("CASE status
                WHEN 'flagged' THEN 1
                WHEN 'hidden' THEN 2
                WHEN 'published' THEN 3
                ELSE 4
            END")->orderByDesc('submitted_at'))
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'published' => __('transport/reviews.status.published'),
                        'flagged' => __('transport/reviews.status.flagged'),
                        'hidden' => __('transport/reviews.status.hidden'),
                    ]),
                Tables\Filters\SelectFilter::make('rating')
                    ->label(__('admin/transport_reviews.filter.rating'))
                    ->options([5 => '5 ★', 4 => '4 ★', 3 => '3 ★', 2 => '2 ★', 1 => '1 ★']),
                Tables\Filters\SelectFilter::make('transporter_tenant_id')
                    ->label(__('admin/transport_reviews.filter.transporter'))
                    ->options(fn () => Tenant::query()->where('type', 'transporter')->pluck('name', 'id')->all())
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('publish')
                    ->label(__('admin/transport_reviews.action.publish'))
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->visible(fn (TransportReview $r) => $r->status !== 'published')
                    ->form([
                        Forms\Components\Textarea::make('moderation_notes')
                            ->label(__('admin/transport_reviews.form.moderation_notes'))
                            ->rows(3),
                    ])
                    ->requiresConfirmation()
                    ->action(fn (TransportReview $record, array $data) => self::moderate($record, 'published', (string) ($data['moderation_notes'] ?? ''))),
                Tables\Actions\Action::make('hide')
                    ->label(__('admin/transport_reviews.action.hide'))
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->visible(fn (TransportReview $r) => $r->status !== 'hidden')
                    ->form([
                        Forms\Components\Textarea::make('moderation_notes')
                            ->label(__('admin/transport_reviews.form.moderation_notes'))
                            ->required()
                            ->rows(4),
                    ])
                    ->requiresConfirmation()
                    ->action(fn (TransportReview $record, array $data) => self::moderate($record, 'hidden', (string) $data['moderation_notes'])),
                Tables\Actions\Action::make('reject')
                    ->label(__('admin/transport_reviews.action.reject'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('moderation_notes')
                            ->label(__('admin/transport_reviews.form.moderation_notes'))
                            ->required()
                            ->rows(4),
                    ])
                    ->requiresConfirmation()
                    ->action(function (TransportReview $record, array $data): void {
                        app(MasterAuditLogger::class)->record(
                            action: 'transport_review.reject',
                            targetType: 'TransportReview',
                            targetId: (string) $record->id,
                            tenantId: (string) $record->transporter_tenant_id,
                            payload: [
                                'rating' => $record->rating,
                                'notes_excerpt' => mb_substr((string) $data['moderation_notes'], 0, 200),
                            ],
                        );
                        TransportReview::forgetAggregateCache($record->transporter_tenant_id);
                        $record->delete();

                        Notification::make()
                            ->danger()
                            ->title(__('admin/transport_reviews.notify.rejected'))
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminTransportReviews::route('/'),
            'view' => Pages\ViewAdminTransportReview::route('/{record}'),
        ];
    }

    private static function moderate(TransportReview $record, string $newStatus, string $notes): void
    {
        $record->forceFill([
            'status' => $newStatus,
            'moderated_by_user_id' => (string) (Auth::id() ?? ''),
            'moderated_at' => now(),
            'moderation_notes' => $notes !== '' ? $notes : $record->moderation_notes,
        ])->save();

        app(MasterAuditLogger::class)->record(
            action: 'transport_review.'.$newStatus,
            targetType: 'TransportReview',
            targetId: (string) $record->id,
            tenantId: (string) $record->transporter_tenant_id,
            payload: [
                'rating' => $record->rating,
                'notes_excerpt' => mb_substr($notes, 0, 200),
            ],
        );

        TransportReview::forgetAggregateCache($record->transporter_tenant_id);

        Notification::make()
            ->success()
            ->title(__('admin/transport_reviews.notify.moderated', ['status' => $newStatus]))
            ->send();
    }
}
