<x-filament-panels::page>
    <form wire:submit="calculate" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit">
                {{ __('transport/calculator.action.submit') }}
            </x-filament::button>
        </div>
    </form>

    @if ($quotation)
        <div class="mt-8 space-y-4">
            <h2 class="text-xl font-bold">{{ __('transport/calculator.result.heading') }}</h2>

            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900">
                    <div class="text-xs uppercase text-gray-500">{{ __('transport/calculator.result.from') }}</div>
                    <div class="font-medium">{{ $fromDisplayName }}</div>
                </div>
                <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900">
                    <div class="text-xs uppercase text-gray-500">{{ __('transport/calculator.result.to') }}</div>
                    <div class="font-medium">{{ $toDisplayName }}</div>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800">
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr>
                            <td class="px-4 py-2 text-gray-500">{{ __('transport/calculator.result.distance') }}</td>
                            <td class="px-4 py-2 text-right font-medium">{{ number_format($quotation->distanceKm, 2, ',', ' ') }} km</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 text-gray-500">{{ __('transport/calculator.result.duration') }}</td>
                            <td class="px-4 py-2 text-right font-medium">{{ floor($quotation->durationSeconds / 3600) }}h {{ floor(($quotation->durationSeconds % 3600) / 60) }}min</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 text-gray-500">{{ __('transport/calculator.result.rate_used') }}</td>
                            <td class="px-4 py-2 text-right font-medium">{{ number_format($quotation->rateUsed, 2, ',', ' ') }} {{ $quotation->currency }}/km</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 text-gray-500">{{ __('transport/calculator.result.base_cost') }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($quotation->baseCost, 2, ',', ' ') }} {{ $quotation->currency }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 text-gray-500">{{ __('transport/calculator.result.fuel_surcharge') }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($quotation->fuelSurcharge, 2, ',', ' ') }} {{ $quotation->currency }}</td>
                        </tr>
                        @if ($quotation->extraHorseFeeTotal > 0)
                            <tr>
                                <td class="px-4 py-2 text-gray-500">{{ __('transport/calculator.result.extra_horse_fee', ['count' => $quotation->horsesCount - 1, 'rate' => number_format($quotation->extraHorseFeePerHead, 2, ',', ' '), 'currency' => $quotation->currency]) }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format($quotation->extraHorseFeeTotal, 2, ',', ' ') }} {{ $quotation->currency }}</td>
                            </tr>
                        @endif
                        @foreach ($quotation->fixedFees as $fee)
                            <tr>
                                <td class="px-4 py-2 text-gray-500">{{ $fee['name'] }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format((float) $fee['amount'], 2, ',', ' ') }} {{ $quotation->currency }}</td>
                            </tr>
                        @endforeach
                        @if ($quotation->minimumAdjustment > 0)
                            <tr>
                                <td class="px-4 py-2 text-gray-500">{{ __('transport/calculator.result.minimum_adjustment') }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format($quotation->minimumAdjustment, 2, ',', ' ') }} {{ $quotation->currency }}</td>
                            </tr>
                        @endif
                        @if ($quotation->surchargeAmount > 0)
                            <tr>
                                <td class="px-4 py-2 text-gray-500">{{ __('transport/calculator.result.surcharge', ['percent' => rtrim(rtrim(number_format($quotation->surchargePercent, 2, ',', ' '), '0'), ',')]) }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format($quotation->surchargeAmount, 2, ',', ' ') }} {{ $quotation->currency }}</td>
                            </tr>
                        @endif
                        <tr class="bg-gray-50 dark:bg-gray-900">
                            <td class="px-4 py-2 font-semibold">{{ __('transport/calculator.result.net_total') }}</td>
                            <td class="px-4 py-2 text-right font-semibold">{{ number_format($quotation->netTotal, 2, ',', ' ') }} {{ $quotation->currency }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 text-gray-500">{{ __('transport/calculator.result.vat', ['rate' => number_format($quotation->vatRate, 0)]) }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($quotation->vatAmount, 2, ',', ' ') }} {{ $quotation->currency }}</td>
                        </tr>
                        <tr class="bg-primary-50 dark:bg-primary-900/30">
                            <td class="px-4 py-3 text-lg font-bold">{{ __('transport/calculator.result.gross_total') }}</td>
                            <td class="px-4 py-3 text-right text-lg font-bold">{{ number_format($quotation->grossTotal, 2, ',', ' ') }} {{ $quotation->currency }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Mapa Leaflet z trasą (decode polyline'a + markery start/end).
                 Patrz docs/MARKETPLACE-ROADMAP.md "Calculator live UX (Leaflet)". --}}
            <x-route-map
                :polyline="$quotation->polyline"
                :fromLat="$pendingPickupLat"
                :fromLng="$pendingPickupLng"
                :toLat="$pendingDropoffLat"
                :toLng="$pendingDropoffLng"
                height="360px"
            />

            <div class="flex items-center justify-between">
                <div class="text-xs text-gray-500">
                    {{ __('transport/calculator.result.routing_via', ['provider' => $quotation->routingProvider]) }}
                </div>
                <x-filament::button wire:click="saveAsQuote" icon="heroicon-o-document-plus">
                    {{ __('transport/calculator.action.save_as_quote') }}
                </x-filament::button>
            </div>
        </div>
    @endif
</x-filament-panels::page>
