<?php

declare(strict_types=1);

namespace App\Filament\Owner\Resources\TransportOrderResource\Pages;

use App\Enums\CalculationMode;
use App\Filament\Owner\Resources\TransportOrderResource;
use App\Models\Central\TransportLead;
use App\Models\Central\TransportLeadResponse;
use App\Models\Tenant\TransportOrder;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Collection;

class ViewTransportOrder extends ViewRecord
{
    protected static string $resource = TransportOrderResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make(__('owner/transport.orders.section.route'))
                ->columns(2)
                ->schema([
                    TextEntry::make('pickup_address')->label(__('owner/transport.orders.label.pickup')),
                    TextEntry::make('dropoff_address')->label(__('owner/transport.orders.label.dropoff')),
                    TextEntry::make('preferred_date')->label(__('owner/transport.orders.label.preferred_date'))->date(),
                    TextEntry::make('preferred_time')->label(__('owner/transport.orders.label.preferred_time')),
                    TextEntry::make('calculation_mode')
                        ->label(__('owner/transport.orders.label.mode'))
                        ->formatStateUsing(fn (?string $state) => $state === null
                            ? '—'
                            : (CalculationMode::tryFrom($state)?->label() ?? $state)),
                ]),

            Section::make(__('owner/transport.orders.section.horse'))
                ->schema([
                    TextEntry::make('horse.name')
                        ->label(__('owner/transport.orders.label.horse'))
                        ->placeholder('—'),
                ]),

            Section::make(__('owner/transport.orders.section.notes'))
                ->visible(fn (TransportOrder $r) => ! empty($r->notes))
                ->schema([
                    TextEntry::make('notes')->label('')->columnSpanFull(),
                ]),

            Section::make(__('owner/transport.orders.section.lifecycle'))
                ->columns(2)
                ->schema([
                    TextEntry::make('status')
                        ->label(__('owner/transport.orders.label.status'))
                        ->badge()
                        ->formatStateUsing(fn (?string $state) => $state === null
                            ? '—'
                            : (TransportOrderResource::statusOptions()[$state] ?? $state)),
                    TextEntry::make('created_at')
                        ->label(__('owner/transport.orders.label.created_at'))
                        ->dateTime(),
                ]),

            Section::make(__('owner/transport.orders.section.responses'))
                ->description(__('owner/transport.orders.section.responses_description'))
                ->schema([
                    TextEntry::make('responses_summary')
                        ->label('')
                        ->state(fn (TransportOrder $r) => $this->renderResponsesSummary($r)),
                ]),
        ]);
    }

    private function renderResponsesSummary(TransportOrder $order): string
    {
        $lead = TransportLead::query()->find($order->central_lead_id);
        if ($lead === null) {
            return __('owner/transport.orders.responses.lead_missing');
        }

        /** @var Collection<int,TransportLeadResponse> $responses */
        $responses = TransportLeadResponse::query()
            ->where('lead_id', $lead->id)
            ->get();

        if ($responses->isEmpty()) {
            return __('owner/transport.orders.responses.none');
        }

        return __('owner/transport.orders.responses.count', ['count' => $responses->count()]);
    }
}
