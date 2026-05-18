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
                        @if ($quotation->minimumAdjustment > 0)
                            <tr>
                                <td class="px-4 py-2 text-gray-500">{{ __('transport/calculator.result.minimum_adjustment') }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format($quotation->minimumAdjustment, 2, ',', ' ') }} {{ $quotation->currency }}</td>
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

            <div class="text-xs text-gray-500">
                {{ __('transport/calculator.result.routing_via', ['provider' => $quotation->routingProvider]) }}
            </div>
        </div>
    @endif
</x-filament-panels::page>
