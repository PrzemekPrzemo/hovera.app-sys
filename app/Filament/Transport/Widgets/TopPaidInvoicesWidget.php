<?php

declare(strict_types=1);

namespace App\Filament\Transport\Widgets;

use App\Domain\Transport\Dashboard\TransportDashboardService;
use App\Filament\Transport\Resources\TransportInvoiceResource;
use App\Models\Tenant\TransportInvoice;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Top 5 zapłaconych FV z ostatnich 90 dni. "Best customers" — pomaga
 * transporterowi szybko zobaczyć kto wniósł największe przychody. Komplementarne
 * do PendingInvoicesWidget (oferty do zafakturowania). Patrz docs/TRANSPORT.md
 * (krok F).
 */
class TopPaidInvoicesWidget extends BaseWidget
{
    protected static ?int $sort = -4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('transport/dashboard.top_paid.heading'))
            ->description(__('transport/dashboard.top_paid.description'))
            ->query(fn () => TransportInvoice::query()->whereRaw('1=0'))
            ->records(fn () => app(TransportDashboardService::class)->topPaidInvoices(5, 90))
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label(__('transport/dashboard.top_paid.number'))
                    ->weight('bold')
                    ->sortable(false),

                Tables\Columns\TextColumn::make('buyer_name')
                    ->label(__('transport/dashboard.top_paid.customer'))
                    ->limit(40)
                    ->sortable(false),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label(__('transport/dashboard.top_paid.paid_at'))
                    ->date()
                    ->sortable(false),

                Tables\Columns\TextColumn::make('total')
                    ->label(__('transport/dashboard.top_paid.total'))
                    ->state(fn (TransportInvoice $i) => $i->total_cents / 100)
                    ->money(fn (TransportInvoice $i) => $i->currency ?? 'PLN')
                    ->alignRight()
                    ->sortable(false),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label(__('transport/dashboard.top_paid.view'))
                    ->icon('heroicon-o-eye')
                    ->url(fn (TransportInvoice $record) => TransportInvoiceResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading(__('transport/dashboard.top_paid.empty_heading'))
            ->emptyStateDescription(__('transport/dashboard.top_paid.empty_description'))
            ->emptyStateIcon('heroicon-o-banknotes')
            ->paginated(false);
    }
}
