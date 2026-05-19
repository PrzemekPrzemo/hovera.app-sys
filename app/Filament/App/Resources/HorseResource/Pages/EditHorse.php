<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\HorseResource\Pages;

use App\Filament\App\Resources\HorseResource;
use App\Tenancy\TenantManager;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHorse extends EditRecord
{
    protected static string $resource = HorseResource::class;

    /**
     * "Zamów transport" — surfaces transport entry-point z karty konia.
     * Pre-fillujemy stable + horse w query (TransportInquiryController odczyta).
     * Visible tylko dla stable na planie z transportem (canUseTransport).
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('order_transport')
                ->label(__('app/transport_entry.horse_action.label'))
                ->icon('heroicon-o-truck')
                ->color('primary')
                ->visible(function (): bool {
                    $tenant = app(TenantManager::class)->current();

                    return $tenant !== null && $tenant->canUseTransport();
                })
                ->url(function (): string {
                    $tenant = app(TenantManager::class)->current();
                    $params = ['from' => 'app'];
                    if ($tenant !== null) {
                        $params['stable'] = $tenant->id;
                    }
                    $params['horse'] = (string) $this->record->getKey();

                    return route('public.transport.inquiry').'?'.http_build_query($params);
                }, shouldOpenInNewTab: true),
        ];
    }
}
