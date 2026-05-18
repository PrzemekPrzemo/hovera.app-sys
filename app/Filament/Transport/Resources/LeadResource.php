<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources;

use App\Filament\Concerns\RestrictedByTenantRole;
use App\Filament\Transport\Resources\LeadResource\Pages;
use App\Models\Central\TransportLead;
use App\Models\Central\TransportLeadDispatch;
use App\Services\Tenancy\TenantRoleGate;
use App\Tenancy\TenantManager;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Inbox leadów dla transportera — pokazuje tylko te leady, które
 * LeadDispatcher (krok 4) wpisał do jego dispatch table. Patrz
 * docs/TRANSPORT.md §5.
 *
 * Read-only — akcje (response z ofertą, dismiss) na ViewLead page.
 */
class LeadResource extends Resource
{
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::FULL_ADMINS_AND_MANAGERS;
    }

    protected static ?string $model = TransportLead::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.dispatch');
    }

    public static function getNavigationLabel(): string
    {
        return __('transport/lead.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('models.transport_lead');
    }

    public static function getPluralModelLabel(): string
    {
        return __('models.transport_leads');
    }

    protected static ?int $navigationSort = 5;

    public static function getNavigationBadge(): ?string
    {
        $tenant = app(TenantManager::class)->current();
        if (! $tenant) {
            return null;
        }

        $count = TransportLeadDispatch::query()
            ->where('transporter_tenant_id', $tenant->id)
            ->where('view_status', 'unseen')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    /**
     * Scope query do leadów które ten transporter dostał (przez dispatcher).
     */
    public static function getEloquentQuery(): Builder
    {
        $tenant = app(TenantManager::class)->current();
        if (! $tenant) {
            return TransportLead::query()->whereRaw('1=0');
        }

        $leadIds = TransportLeadDispatch::query()
            ->where('transporter_tenant_id', $tenant->id)
            ->pluck('lead_id')
            ->all();

        return TransportLead::query()->whereIn('id', $leadIds);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('originator_name')
                    ->label(__('transport/lead.table.column.customer'))
                    ->searchable(['originator_name', 'originator_email'])
                    ->description(fn (TransportLead $l) => $l->originator_email),
                Tables\Columns\TextColumn::make('route')
                    ->label(__('transport/lead.table.column.route'))
                    ->state(fn (TransportLead $l) => $l->pickup_address.' → '.$l->dropoff_address)
                    ->limit(60),
                Tables\Columns\TextColumn::make('preferred_date')
                    ->label(__('transport/lead.table.column.preferred_date'))
                    ->date()
                    ->sortable()
                    ->description(fn (TransportLead $l) => $l->preferred_time),
                Tables\Columns\TextColumn::make('horse_count')
                    ->label(__('transport/lead.table.column.horse_count'))
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('transport/lead.table.column.status'))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'open' => 'info',
                        'quoted' => 'warning',
                        'accepted' => 'success',
                        'rejected', 'expired', 'cancelled' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('transport/lead.table.column.expires_at'))
                    ->dateTime()
                    ->sortable()
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : ($state && $state->isBefore(now()->addDay()) ? 'warning' : null)),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('transport/lead.table.column.created_at'))
                    ->dateTime()
                    ->since()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => __('enums.transport_lead_status.open'),
                        'quoted' => __('enums.transport_lead_status.quoted'),
                        'accepted' => __('enums.transport_lead_status.accepted'),
                        'rejected' => __('enums.transport_lead_status.rejected'),
                        'expired' => __('enums.transport_lead_status.expired'),
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeads::route('/'),
            'view' => Pages\ViewLead::route('/{record}'),
        ];
    }
}
