<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Services\MasterAuditLogger;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Master-admin: read-only overview of API tokens issued to tenant users
 * (mobile app, third-party integrations). Filtering by tenant + activity
 * helps spot dormant tokens that should be cleaned up before a leak
 * becomes a problem.
 *
 * Distinct from /admin/api-tokens which lists ONLY the current admin's
 * tokens (for own monitoring scripts) — this page surfaces every token
 * issued for a user with a tenant membership.
 */
class TenantApiTokens extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 51;

    protected static string $view = 'filament.admin.pages.tenant-api-tokens';

    public static function getNavigationLabel(): string
    {
        return __('admin/api-management.tenant_tokens.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.configuration');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin/api-management.tenant_tokens.title');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user !== null && (bool) ($user->is_master_admin ?? false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->tokenQuery())
            ->columns([
                Tables\Columns\TextColumn::make('user.email')
                    ->label(__('admin/api-management.tenant_tokens.col.user'))
                    ->getStateUsing(function (PersonalAccessToken $record): string {
                        $user = User::query()->find($record->tokenable_id);

                        return $user?->email ?? '—';
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereIn('tokenable_id', User::query()
                            ->where('email', 'like', "%{$search}%")
                            ->pluck('id'));
                    }),
                Tables\Columns\TextColumn::make('tenant')
                    ->label(__('admin/api-management.tenant_tokens.col.tenant'))
                    ->getStateUsing(function (PersonalAccessToken $record): string {
                        $tenantIds = TenantMembership::query()
                            ->where('user_id', $record->tokenable_id)
                            ->whereNull('revoked_at')
                            ->pluck('tenant_id');

                        return Tenant::query()
                            ->whereIn('id', $tenantIds)
                            ->pluck('name')
                            ->join(', ') ?: '—';
                    }),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin/api-management.tenant_tokens.col.name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('abilities')
                    ->label(__('admin/api-management.tenant_tokens.col.abilities'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => is_array($state) ? $state : (array) $state)
                    ->separator(','),
                Tables\Columns\TextColumn::make('last_used_at')
                    ->label(__('admin/api-management.tenant_tokens.col.last_used_at'))
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin/api-management.tenant_tokens.col.created_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('issued_ip')
                    ->label(__('admin/api-management.tenant_tokens.col.ip'))
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('issued_user_agent')
                    ->label(__('admin/api-management.tenant_tokens.col.user_agent'))
                    ->placeholder('—')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label(__('admin/api-management.tenant_tokens.filter.tenant'))
                    ->options(fn () => Tenant::query()->orderBy('name')->pluck('name', 'id')->toArray())
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }
                        $userIds = TenantMembership::query()
                            ->where('tenant_id', $data['value'])
                            ->whereNull('revoked_at')
                            ->pluck('user_id');

                        return $query->whereIn('tokenable_id', $userIds);
                    }),
                Tables\Filters\Filter::make('activity')
                    ->label(__('admin/api-management.tenant_tokens.filter.activity'))
                    ->form([
                        Select::make('state')
                            ->options([
                                'active' => __('admin/api-management.tenant_tokens.filter.active_30d'),
                                'dormant' => __('admin/api-management.tenant_tokens.filter.dormant'),
                            ])
                            ->placeholder(__('admin/api-management.tenant_tokens.filter.any')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['state'] ?? null) {
                            'active' => $query->where('last_used_at', '>=', now()->subDays(30)),
                            'dormant' => $query->where(fn ($q) => $q->whereNull('last_used_at')->orWhere('last_used_at', '<', now()->subDays(30))),
                            default => $query,
                        };
                    }),
                Tables\Filters\Filter::make('created_range')
                    ->label(__('admin/api-management.tenant_tokens.filter.created_range'))
                    ->form([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('revoke')
                    ->label(__('admin/api-management.tenant_tokens.action.revoke'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('admin/api-management.tenant_tokens.action.revoke_confirm'))
                    ->action(function (PersonalAccessToken $record, MasterAuditLogger $audit): void {
                        $audit->record('api_token.revoked_by_admin', 'PersonalAccessToken', (string) $record->id, null, [
                            'name' => $record->name,
                            'tokenable_id' => $record->tokenable_id,
                        ]);
                        $record->delete();
                        Notification::make()
                            ->success()
                            ->title(__('admin/api-management.tenant_tokens.action.revoke_success'))
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_revoke')
                    ->label(__('admin/api-management.tenant_tokens.bulk.revoke'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($records, MasterAuditLogger $audit): void {
                        $count = 0;
                        foreach ($records as $token) {
                            $audit->record('api_token.revoked_by_admin', 'PersonalAccessToken', (string) $token->id, null, [
                                'name' => $token->name,
                                'tokenable_id' => $token->tokenable_id,
                                'bulk' => true,
                            ]);
                            $token->delete();
                            $count++;
                        }
                        Notification::make()
                            ->success()
                            ->title(__('admin/api-management.tenant_tokens.bulk.revoked', ['count' => $count]))
                            ->send();
                    }),
            ]);
    }

    protected function tokenQuery(): Builder
    {
        // Tokens whose tokenable is a central User AND that user has at least
        // one active tenant membership. Master-admin's own tokens (no
        // memberships) are excluded — they live on the dedicated /api-tokens
        // page instead.
        $userIds = TenantMembership::query()
            ->whereNull('revoked_at')
            ->pluck('user_id')
            ->unique();

        return PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $userIds);
    }
}
