<?php

declare(strict_types=1);

namespace App\Filament\Owner\Resources\TransportOrderResource\Pages;

use App\Enums\CalculationMode;
use App\Filament\Owner\Resources\TransportOrderResource;
use App\Models\Central\Tenant;
use App\Models\Central\TransportLead;
use App\Models\Central\TransportLeadResponse;
use App\Models\Tenant\TransportOrder;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
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
                    ViewEntry::make('responses_list')
                        ->label('')
                        ->view('filament.owner.partials.transport-order-responses')
                        ->state(fn (TransportOrder $r) => ['responses' => $this->loadResponses($r)])
                        ->visible(fn (TransportOrder $r) => count($this->loadResponses($r)) > 0),
                ]),
        ]);
    }

    private function renderResponsesSummary(TransportOrder $order): string
    {
        $lead = TransportLead::query()->find($order->central_lead_id);
        if ($lead === null) {
            return __('owner/transport.orders.responses.lead_missing');
        }

        $count = count($this->loadResponses($order));
        if ($count === 0) {
            return __('owner/transport.orders.responses.none');
        }

        return __('owner/transport.orders.responses.count', ['count' => $count]);
    }

    /**
     * Lista odpowiedzi przewoźników z danymi do listy (nazwa, cena, link
     * do public landing'a). Cache'owana w `$this->loaded` per request,
     * bo `renderResponsesSummary` i `ViewEntry::state()` wołają to obie.
     *
     * @return list<array{transporter_name:string, price:string, currency:string, date:string, link:?string}>
     */
    private array $loaded = [];

    /** @return list<array{transporter_name:string, price:string, currency:string, date:string, link:?string}> */
    private function loadResponses(TransportOrder $order): array
    {
        $key = (string) $order->id;
        if (isset($this->loaded[$key])) {
            return $this->loaded[$key];
        }

        $lead = TransportLead::query()->find($order->central_lead_id);
        if ($lead === null) {
            return $this->loaded[$key] = [];
        }

        /** @var Collection<int,TransportLeadResponse> $responses */
        $responses = TransportLeadResponse::query()
            ->where('lead_id', $lead->id)
            ->orderBy('price_gross')
            ->get();

        if ($responses->isEmpty()) {
            return $this->loaded[$key] = [];
        }

        $tenantNames = Tenant::query()
            ->whereIn('id', $responses->pluck('transporter_tenant_id')->unique()->all())
            ->pluck('name', 'id')
            ->all();

        $out = [];
        foreach ($responses as $r) {
            $out[] = [
                'transporter_name' => (string) ($tenantNames[$r->transporter_tenant_id] ?? '—'),
                'price' => number_format((float) $r->price_gross, 2, ',', ' '),
                'currency' => (string) $r->currency,
                'date' => $r->proposed_date?->format('Y-m-d') ?? '',
                // pdf_url to typowo link do public quote landing — klik → akceptacja.
                'link' => $r->pdf_url ? (string) $r->pdf_url : null,
            ];
        }

        return $this->loaded[$key] = $out;
    }
}
