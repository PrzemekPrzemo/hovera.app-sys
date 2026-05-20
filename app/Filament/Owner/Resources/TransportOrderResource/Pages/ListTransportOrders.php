<?php

declare(strict_types=1);

namespace App\Filament\Owner\Resources\TransportOrderResource\Pages;

use App\Filament\Owner\Resources\TransportOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTransportOrders extends ListRecords
{
    protected static string $resource = TransportOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create')
                ->label(__('owner/transport.orders.action.create'))
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->url(fn () => route('filament.owner.pages.order-transport')),
        ];
    }
}
