<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources\TransportInvoiceResource\Pages;

use App\Enums\TransportInvoiceStatus;
use App\Filament\Transport\Resources\TransportInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTransportInvoice extends ViewRecord
{
    protected static string $resource = TransportInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('downloadPdf')
                ->label(__('transport/invoice_resource.action.download_pdf'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => TransportInvoiceResource::downloadPdf($this->record)),
            Actions\Action::make('sendEmail')
                ->label(__('transport/invoice_resource.action.send_email'))
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->visible(fn () => $this->record->buyer_email && ! $this->record->status->isFinal())
                ->requiresConfirmation()
                ->action(fn () => TransportInvoiceResource::sendInvoiceEmail($this->record)),
            Actions\Action::make('markPaid')
                ->label(__('transport/invoice_resource.action.mark_paid'))
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => in_array($this->record->status, [TransportInvoiceStatus::Issued, TransportInvoiceStatus::Overdue], true))
                ->requiresConfirmation()
                ->action(function () {
                    TransportInvoiceResource::markPaid($this->record);
                    $this->refreshFormData(['status', 'paid_at']);
                }),
        ];
    }
}
