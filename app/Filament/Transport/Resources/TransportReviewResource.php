<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources;

use App\Filament\Concerns\RestrictedByTenantRole;
use App\Filament\Transport\Resources\TransportReviewResource\Pages;
use App\Mail\MasterAdmin\ReviewFlaggedMail;
use App\Models\Central\Tenant;
use App\Models\Central\TransportReview;
use App\Services\Tenancy\TenantRoleGate;
use App\Tenancy\TenantManager;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Lista recenzji widoczna w panelu /transport — read-only z dwoma akcjami:
 *
 *   - "Odpowiedz publicznie" — modal z textareą, zapisuje
 *     `transporter_response` + timestamp. Edycja dozwolona (idempotent).
 *   - "Zgłoś do moderacji" — flaguje review (status=flagged + reason),
 *     review przestaje być widoczny publicznie do czasu decyzji master
 *     admin'a (publish/hide).
 *
 * Scope query do recenzji tego konkretnego tenant'a (transporter_tenant_id
 * = current tenant). Patrz docs/TRANSPORT.md §12.
 */
class TransportReviewResource extends Resource
{
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::TRANSPORT_OPERATORS;
    }

    protected static ?string $model = TransportReview::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.dispatch');
    }

    public static function getNavigationLabel(): string
    {
        return __('transport/reviews.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('transport/reviews.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('transport/reviews.model.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = app(TenantManager::class)->current();
        if (! $tenant) {
            return TransportReview::query()->whereRaw('1=0');
        }

        return TransportReview::query()
            ->where('transporter_tenant_id', $tenant->id)
            ->whereNotNull('submitted_at');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('rating')
                    ->label(__('transport/reviews.table.column.rating'))
                    ->formatStateUsing(fn ($state) => str_repeat('★', (int) $state).str_repeat('☆', max(0, 5 - (int) $state)))
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label(__('transport/reviews.table.column.customer'))
                    ->formatStateUsing(fn ($state) => TransportReview::redactCustomerName($state))
                    ->searchable(['customer_name']),
                Tables\Columns\TextColumn::make('comment')
                    ->label(__('transport/reviews.table.column.comment'))
                    ->limit(80)
                    ->wrap(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('transport/reviews.table.column.status'))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'published' => 'success',
                        'flagged' => 'warning',
                        'hidden' => 'gray',
                        'expired' => 'gray',
                        default => 'info',
                    })
                    ->formatStateUsing(fn ($state) => __('transport/reviews.status.'.$state)),
                Tables\Columns\IconColumn::make('transporter_response')
                    ->label(__('transport/reviews.table.column.responded'))
                    ->boolean()
                    ->getStateUsing(fn (TransportReview $r) => ! empty($r->transporter_response)),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->label(__('transport/reviews.table.column.submitted_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'published' => __('transport/reviews.status.published'),
                        'flagged' => __('transport/reviews.status.flagged'),
                        'hidden' => __('transport/reviews.status.hidden'),
                    ]),
                Tables\Filters\SelectFilter::make('rating')
                    ->label(__('transport/reviews.filter.rating'))
                    ->options([5 => '5 ★', 4 => '4 ★', 3 => '3 ★', 2 => '2 ★', 1 => '1 ★']),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('respond')
                    ->label(__('transport/reviews.action.respond'))
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('primary')
                    ->visible(fn (TransportReview $r) => in_array($r->status, ['published', 'flagged'], true))
                    ->form([
                        Forms\Components\Textarea::make('transporter_response')
                            ->label(__('transport/reviews.form.response_label'))
                            ->rows(5)
                            ->maxLength(2000)
                            ->required()
                            ->helperText(__('transport/reviews.form.response_helper')),
                    ])
                    ->fillForm(fn (TransportReview $r): array => [
                        'transporter_response' => $r->transporter_response,
                    ])
                    ->action(function (TransportReview $record, array $data): void {
                        $record->forceFill([
                            'transporter_response' => mb_substr((string) $data['transporter_response'], 0, 2000),
                            'transporter_responded_at' => now(),
                        ])->save();

                        TransportReview::forgetAggregateCache($record->transporter_tenant_id);

                        Notification::make()
                            ->success()
                            ->title(__('transport/reviews.notify.response_saved'))
                            ->send();
                    }),
                Tables\Actions\Action::make('flag')
                    ->label(__('transport/reviews.action.flag'))
                    ->icon('heroicon-o-flag')
                    ->color('warning')
                    ->visible(fn (TransportReview $r) => $r->status === 'published')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label(__('transport/reviews.form.flag_reason_label'))
                            ->rows(4)
                            ->minLength(10)
                            ->maxLength(1000)
                            ->required()
                            ->helperText(__('transport/reviews.form.flag_reason_helper')),
                    ])
                    ->requiresConfirmation()
                    ->action(function (TransportReview $record, array $data): void {
                        $userId = (string) (Auth::id() ?? '');
                        $record->forceFill([
                            'status' => 'flagged',
                            'flagged_reason' => (string) $data['reason'],
                            'flagged_by_tenant_at' => now(),
                            'flagged_by_user_id' => $userId !== '' ? $userId : null,
                        ])->save();

                        TransportReview::forgetAggregateCache($record->transporter_tenant_id);

                        // Powiadom master admin'ów — triage queue w
                        // `/admin/transport-reviews` filter „Tylko flagged".
                        self::dispatchFlaggedReviewMail($record);

                        Notification::make()
                            ->warning()
                            ->title(__('transport/reviews.notify.flagged_title'))
                            ->body(__('transport/reviews.notify.flagged_body'))
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransportReviews::route('/'),
            'view' => Pages\ViewTransportReview::route('/{record}'),
        ];
    }

    /**
     * Wysyła mail do master admin'ów (users.is_master_admin=true) po
     * tym jak transporter zaflagował review. Master admin triage'uje
     * w `/admin/transport-reviews?filter=flagged`.
     *
     * Soft-fail — jeśli mail backend padnie, audit log nie wpada (review
     * jest już oznaczony jako flagged w DB, admin zobaczy go w panel'u
     * niezależnie od mail'a).
     */
    private static function dispatchFlaggedReviewMail(TransportReview $review): void
    {
        try {
            $recipients = DB::connection('central')
                ->table('users')
                ->where('is_master_admin', true)
                ->whereNull('deleted_at')
                ->pluck('email')
                ->filter()
                ->values()
                ->all();

            if ($recipients === []) {
                return;
            }

            $transporter = Tenant::query()->find($review->transporter_tenant_id);
            $flaggedByEmail = (string) DB::connection('central')
                ->table('users')
                ->where('id', $review->flagged_by_user_id)
                ->value('email') ?: 'unknown@hovera.app';

            Mail::to($recipients)->send(
                new ReviewFlaggedMail(
                    review: $review,
                    transporterName: (string) ($transporter?->name ?? '—'),
                    flaggedByEmail: $flaggedByEmail,
                ),
            );
        } catch (\Throwable $e) {
            Log::warning('ReviewFlaggedMail dispatch failed', [
                'review_id' => $review->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
