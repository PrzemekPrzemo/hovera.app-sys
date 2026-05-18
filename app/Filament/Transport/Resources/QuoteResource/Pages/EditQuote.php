<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources\QuoteResource\Pages;

use App\Enums\QuoteStatus;
use App\Filament\Transport\Resources\QuoteResource;
use App\Services\TenantAuditLogger;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditQuote extends EditRecord
{
    protected static string $resource = QuoteResource::class;

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
