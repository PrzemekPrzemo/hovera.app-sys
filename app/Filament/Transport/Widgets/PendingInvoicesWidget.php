<?php

declare(strict_types=1);

namespace App\Filament\Transport\Widgets;

use App\Domain\Transport\Dashboard\TransportDashboardService;
use App\Filament\Transport\Resources\QuoteResource;
use App\Models\Tenant\Quote;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Top 5 accepted ofert bez wystawionej FV — księgowy backlog. Patrz
 * docs/TRANSPORT.md (krok E z feedbacku prod).
 */
class PendingInvoicesWidget extends BaseWidget
{
    protected static ?int $sort = -5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('transport/dashboard.pending_invoices.heading'))
            ->description(__('transport/dashboard.pending_invoices.description'))
            ->query(fn () => Quote::query()->whereRaw('1=0'))   // placeholder; rzeczywista lista przez records()
            ->records(fn () => app(TransportDashboardService::class)->pendingInvoices(5))
            ->columns([
                Tables\Columns\TextColumn::make('number')->weight('bold')->searchable(false)->sortable(false),
                Tables\Columns\TextColumn::make('customer_name')->label(__('transport/dashboard.pending_invoices.customer')),
                Tables\Columns\TextColumn::make('accepted_at')->date()->label(__('transport/dashboard.pending_invoices.accepted_at')),
                Tables\Columns\TextColumn::make('gross_total')
                    ->label(__('transport/dashboard.pending_invoices.gross_total'))
                    ->money(fn (Quote $q) => $q->currency),
            ])
            ->actions([
                Tables\Actions\Action::make('issue')
                    ->label(__('transport/dashboard.pending_invoices.issue'))
                    ->icon('heroicon-o-document-currency-dollar')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (Quote $record) => QuoteResource::issueInvoice($record)),
            ])
            ->paginated(false);
    }
}
