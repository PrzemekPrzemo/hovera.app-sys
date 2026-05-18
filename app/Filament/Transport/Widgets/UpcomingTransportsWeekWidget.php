<?php

declare(strict_types=1);

namespace App\Filament\Transport\Widgets;

use App\Domain\Transport\Dashboard\TransportDashboardService;
use App\Filament\Transport\Resources\QuoteResource;
use App\Models\Tenant\Quote;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Carbon;

/**
 * Najbliższe 7 dni — zaakceptowane (lub zafakturowane) oferty. Komplementarne
 * do UpcomingTransportsWidget (dziś + jutro) — daje dispatcherowi szerszy
 * horyzont planowania. Patrz docs/TRANSPORT.md (krok F).
 */
class UpcomingTransportsWeekWidget extends BaseWidget
{
    protected static ?int $sort = -6;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('transport/dashboard.upcoming_week.heading'))
            ->description(__('transport/dashboard.upcoming_week.description'))
            ->query(fn () => Quote::query()->whereRaw('1=0'))
            ->records(fn () => app(TransportDashboardService::class)->upcomingTransportsWeek(7, 10))
            ->columns([
                Tables\Columns\TextColumn::make('preferred_date')
                    ->label(__('transport/dashboard.upcoming_week.date'))
                    ->date()
                    ->badge()
                    ->color(fn (Quote $q) => $this->dateColor($q->preferred_date))
                    ->sortable(false),

                Tables\Columns\TextColumn::make('customer_name')
                    ->label(__('transport/dashboard.upcoming_week.customer'))
                    ->limit(40)
                    ->sortable(false),

                Tables\Columns\TextColumn::make('route')
                    ->label(__('transport/dashboard.upcoming_week.route'))
                    ->state(fn (Quote $q) => self::shortCity($q->pickup_address).' → '.self::shortCity($q->dropoff_address))
                    ->sortable(false),

                Tables\Columns\TextColumn::make('driver')
                    ->label(__('transport/dashboard.upcoming_week.driver'))
                    ->state(fn (Quote $q) => $q->driver?->full_name ?? '—')
                    ->sortable(false),

                Tables\Columns\TextColumn::make('gross_total')
                    ->label(__('transport/dashboard.upcoming_week.gross'))
                    ->money(fn (Quote $q) => $q->currency ?? 'PLN')
                    ->alignRight()
                    ->sortable(false),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label(__('transport/dashboard.upcoming_week.view'))
                    ->icon('heroicon-o-eye')
                    ->url(fn (Quote $record) => QuoteResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading(__('transport/dashboard.upcoming_week.empty_heading'))
            ->emptyStateDescription(__('transport/dashboard.upcoming_week.empty_description'))
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->emptyStateActions([
                Tables\Actions\Action::make('calculator')
                    ->label(__('transport/dashboard.upcoming_week.empty_action'))
                    ->icon('heroicon-o-calculator')
                    ->url(url('/transport/calculator')),
            ])
            ->paginated(false);
    }

    private function dateColor(mixed $date): string
    {
        if ($date === null) {
            return 'gray';
        }
        $d = $date instanceof \DateTimeInterface ? Carbon::instance($date) : Carbon::parse((string) $date);
        $today = Carbon::today();
        if ($d->isSameDay($today)) {
            return 'danger';
        }
        if ($d->isSameDay($today->copy()->addDay())) {
            return 'warning';
        }

        return 'gray';
    }

    private static function shortCity(?string $address): string
    {
        if (! $address) {
            return '—';
        }
        $parts = explode(',', $address, 2);

        return trim($parts[count($parts) - 1] ?: $parts[0]);
    }
}
