<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Models\Central\Tenant;
use App\Services\Master\MasterMetrics;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Top tenants by recent activity, with a live-computed health score
 * pill so support can spot at-risk accounts at a glance.
 */
class TenantHealthTableWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Stajnie — aktywność i zdrowie';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Tenant::query()
                ->with('plan')
                ->whereIn('status', ['active', 'trialing', 'past_due', 'suspended'])
                ->orderByDesc('last_activity_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Stajnia')
                    ->description(fn (Tenant $t) => $t->slug)
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => fn ($state) => in_array($state, ['active', 'trialing'], true),
                        'warning' => 'past_due',
                        'danger' => fn ($state) => in_array($state, ['suspended', 'churned'], true),
                        'gray' => 'provisioning',
                    ]),
                Tables\Columns\TextColumn::make('plan.name')
                    ->label('Plan')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('last_activity_at')
                    ->label('Ostatnia aktywność')
                    ->since()
                    ->placeholder('Brak'),
                Tables\Columns\TextColumn::make('health_score')
                    ->label('Health')
                    ->state(fn (Tenant $t) => app(MasterMetrics::class)->liveHealth($t)['score'])
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 80 => 'success',
                        $state >= 50 => 'primary',
                        $state >= 30 => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (int $state) => $state.' / 100'),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->recordUrl(fn (Tenant $t) => route('filament.admin.resources.tenants.edit', $t));
    }
}
