<?php

declare(strict_types=1);

namespace App\Filament\Owner\Resources\HorseResource\Pages;

use App\Filament\Owner\Resources\HorseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHorse extends EditRecord
{
    protected static string $resource = HorseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('order_transport')
                ->label(__('owner/horses.action.order_transport'))
                ->icon('heroicon-o-truck')
                ->color('primary')
                ->url(fn () => route('filament.owner.pages.order-transport', [
                    'horse' => (string) $this->record->getKey(),
                ])),
            Actions\DeleteAction::make(),
        ];
    }
}
