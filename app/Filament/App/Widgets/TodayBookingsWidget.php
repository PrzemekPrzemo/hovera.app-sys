<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Enums\CalendarEntryStatus;
use App\Models\Tenant\CalendarEntry;
use App\Tenancy\TenantManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Today's bookings table — hour, horse, instructor, arena, status.
 * Sits below TodayStatsWidget on the /app dashboard.
 */
class TodayBookingsWidget extends BaseWidget
{
    protected static ?int $sort = -4;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('app/dashboard.today.bookings_table_heading');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                if (! app(TenantManager::class)->hasTenant()) {
                    return CalendarEntry::query()->whereRaw('1 = 0');
                }

                return CalendarEntry::query()
                    ->with(['horse', 'instructor', 'arena'])
                    ->whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()])
                    ->whereNotIn('status', [
                        CalendarEntryStatus::Cancelled->value,
                        CalendarEntryStatus::NoShow->value,
                    ])
                    ->orderBy('starts_at');
            })
            ->columns([
                Tables\Columns\TextColumn::make('starts_at')
                    ->label(__('app/dashboard.today.col_time'))
                    ->time('H:i')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('horse.name')
                    ->label(__('app/dashboard.today.col_horse'))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('instructor.name')
                    ->label(__('app/dashboard.today.col_instructor'))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('arena.name')
                    ->label(__('app/dashboard.today.col_arena'))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('app/dashboard.today.col_status'))
                    ->badge()
                    ->formatStateUsing(fn (CalendarEntryStatus $state): string => $state->label())
                    ->color(fn (CalendarEntryStatus $state): string => match ($state) {
                        CalendarEntryStatus::Requested => 'warning',
                        CalendarEntryStatus::Confirmed => 'success',
                        CalendarEntryStatus::Completed => 'gray',
                        default => 'gray',
                    }),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('app/dashboard.today.empty_heading'))
            ->emptyStateDescription(__('app/dashboard.today.empty_desc'));
    }
}
