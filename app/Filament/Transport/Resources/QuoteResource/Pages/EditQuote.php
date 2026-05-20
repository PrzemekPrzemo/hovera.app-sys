<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources\QuoteResource\Pages;

use App\Enums\QuoteStatus;
use App\Filament\Transport\Resources\QuoteResource;
use App\Models\Tenant\Quote;
use App\Services\TenantAuditLogger;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditQuote extends EditRecord
{
    protected static string $resource = QuoteResource::class;

    /**
     * Line_items dolicza się DO net_total, VAT i gross są przeliczane.
     * Patrz docs/MARKETPLACE-ROADMAP.md "Calculator: quote_items line items".
     *
     * Trick: $data['net_total'] z form'a JEST już INCLUSIVE poprzedniego
     * line_items (record był wczytany z DB z items+net). Żeby dodawanie/
     * modyfikacja items'ów było idempotent'ne, odejmujemy starą sumę items'ów
     * (z record->line_items) ZANIM dodamy nową.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $oldItems = is_array($this->record->line_items) ? $this->record->line_items : [];
        $oldItemsTotal = array_sum(array_map(fn ($i) => (float) ($i['line_total_net'] ?? 0), $oldItems));

        $rawItems = $data['line_items'] ?? [];
        $newItems = is_array($rawItems) ? Quote::normaliseLineItems($rawItems) : [];
        $data['line_items'] = $newItems;
        $newItemsTotal = array_sum(array_column($newItems, 'line_total_net'));

        $delta = round($newItemsTotal - $oldItemsTotal, 2);
        if ($delta !== 0.0) {
            $netTotal = round((float) ($data['net_total'] ?? 0) + $delta, 2);
            $vatRate = (float) ($data['vat_rate'] ?? 0);
            $vatAmount = round($netTotal * ($vatRate / 100), 2);

            $data['net_total'] = $netTotal;
            $data['vat_amount'] = $vatAmount;
            $data['gross_total'] = round($netTotal + $vatAmount, 2);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('downloadPdf')
                ->label(__('transport/quote.action.download_pdf'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => QuoteResource::downloadPdf($this->record)),
            Actions\Action::make('send')
                ->label(__('transport/quote.action.send'))
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->visible(fn () => $this->record->status === QuoteStatus::Draft)
                ->requiresConfirmation()
                ->action(function () {
                    QuoteResource::sendQuote($this->record);
                    $this->refreshFormData(['status', 'sent_at', 'accept_token']);
                }),
            Actions\Action::make('withdraw')
                ->label(__('transport/quote.action.withdraw'))
                ->icon('heroicon-o-x-circle')
                ->color('warning')
                ->visible(fn () => $this->record->status === QuoteStatus::Sent)
                ->requiresConfirmation()
                ->action(function () {
                    QuoteResource::withdrawQuote($this->record);
                    $this->refreshFormData(['status', 'withdrawn_at']);
                }),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        app(TenantAuditLogger::class)->record(
            'quote.update',
            'Quote',
            (string) $this->record->getKey(),
            ['number' => $this->record->number],
        );
    }
}
