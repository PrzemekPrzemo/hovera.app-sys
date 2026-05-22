<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\AuditLogMasterResource\Pages;
use App\Models\Central\AuditLogMaster;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Master-admin audit log viewer — append-only przeglądarka wpisów
 * zapisywanych przez `MasterAuditLogger` (tenant.destroy, tenant.bulk_purge,
 * impersonation.start, ksef.send itd.).
 *
 * Read-only — żadna z metod canCreate/canEdit/canDelete nie pozwala
 * modyfikować wpisów (audit log MUSI być immutable do compliance —
 * jeśli ktoś mógłby kasować, audyt traci wartość).
 *
 * Wartość biznesowa:
 *   - RODO art. 15: gdy klient pyta "co robiliście z moimi danymi" —
 *     wyciąg z filtra `tenant_id = X` w 30 sekund
 *   - Debug: kiedy coś się popsuło — kto/kiedy/co zmieniał
 *   - Security: impersonation events, niespodziewane purge'y
 */
class AuditLogMasterResource extends Resource
{
    protected static ?string $model = AuditLogMaster::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 80;

    public static function getNavigationLabel(): string
    {
        return __('admin/audit_log.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.configuration');
    }

    public static function getModelLabel(): string
    {
        return __('admin/audit_log.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/audit_log.model_plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin/audit_log.column.timestamp'))
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('actor.email')
                    ->label(__('admin/audit_log.column.actor'))
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('tenant.slug')
                    ->label(__('admin/audit_log.column.tenant'))
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('action')
                    ->label(__('admin/audit_log.column.action'))
                    ->badge()
                    ->color(fn (string $state) => self::actionColor($state))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('target_type')
                    ->label(__('admin/audit_log.column.target'))
                    ->formatStateUsing(fn (?string $state, AuditLogMaster $record) => $state
                        ? $state.':'.$record->target_id
                        : '—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label(__('admin/audit_log.column.ip'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('actor_user_id')
                    ->label(__('admin/audit_log.filter.actor'))
                    ->options(fn () => User::query()
                        ->where('is_master_admin', true)
                        ->orderBy('email')
                        ->pluck('email', 'id')
                        ->all())
                    ->searchable(),
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label(__('admin/audit_log.filter.tenant'))
                    ->options(fn () => Tenant::query()
                        ->orderBy('slug')
                        ->limit(200)
                        ->pluck('slug', 'id')
                        ->all())
                    ->searchable(),
                Filter::make('date_range')
                    ->form([
                        DatePicker::make('from')
                            ->label(__('admin/audit_log.filter.from')),
                        DatePicker::make('until')
                            ->label(__('admin/audit_log.filter.until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading(fn (AuditLogMaster $record) => $record->action)
                    ->infolist([
                        TextEntry::make('created_at')
                            ->label(__('admin/audit_log.column.timestamp'))
                            ->dateTime('Y-m-d H:i:s'),
                        TextEntry::make('actor.email')
                            ->label(__('admin/audit_log.column.actor')),
                        TextEntry::make('tenant.slug')
                            ->label(__('admin/audit_log.column.tenant')),
                        TextEntry::make('action')
                            ->label(__('admin/audit_log.column.action'))
                            ->badge(),
                        TextEntry::make('target_type')
                            ->label(__('admin/audit_log.column.target_type')),
                        TextEntry::make('target_id')
                            ->label(__('admin/audit_log.column.target_id')),
                        TextEntry::make('ip_address')
                            ->label(__('admin/audit_log.column.ip')),
                        TextEntry::make('user_agent')
                            ->label(__('admin/audit_log.column.user_agent'))
                            ->columnSpanFull(),
                        KeyValueEntry::make('payload')
                            ->label(__('admin/audit_log.column.payload'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Kolor badge'a per akcja — destruktywne na czerwono (auditor szybko
     * wyławia), neutralne na szaro, modyfikacje na żółto.
     */
    private static function actionColor(string $action): string
    {
        if (str_contains($action, 'destroy') || str_contains($action, 'purge') || str_contains($action, 'delete')) {
            return 'danger';
        }
        if (str_contains($action, 'impersonation') || str_contains($action, 'suspend')) {
            return 'warning';
        }
        if (str_contains($action, 'create') || str_contains($action, 'verify') || str_contains($action, 'activate')) {
            return 'success';
        }

        return 'gray';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogEntries::route('/'),
        ];
    }
}
