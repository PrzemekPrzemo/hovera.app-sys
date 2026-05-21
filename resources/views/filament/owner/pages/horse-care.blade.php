<x-filament-panels::page>
    @php($stable = $this->stableTenant)
    @php($latest = $this->latestWeight())

    {{-- Header: stable context (parity z innymi owner page'ami) --}}
    <header class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900/40">
        <div class="text-xs uppercase tracking-wide text-gray-500">
            {{ __('owner/horse_care.header.label') }}
        </div>
        <div class="text-lg font-semibold">{{ $stable->name }}</div>
    </header>

    {{-- Sekcja: Waga --}}
    <section class="space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold">{{ __('owner/horse_care.weight.heading') }}</h2>
            @if ($latest !== null)
                <div class="text-sm text-gray-500">
                    {{ __('owner/horse_care.weight.latest_prefix') }}
                    <strong class="text-base text-gray-900 dark:text-gray-100">{{ $this->formatWeight($latest->weightKg) }}</strong>
                    <span class="text-xs">({{ $latest->measuredAt->format('Y-m-d') }})</span>
                </div>
            @endif
        </div>

        @if (empty($this->weights))
            <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/40">
                {{ __('owner/horse_care.weight.empty') }}
            </div>
        @else
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-4 py-2">{{ __('owner/horse_care.weight.col.measured_at') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('owner/horse_care.weight.col.weight') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('owner/horse_care.weight.col.delta') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('owner/horse_care.weight.col.girth') }}</th>
                            <th class="px-4 py-2">{{ __('owner/horse_care.weight.col.notes') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Reverse order: najnowsze na górze (service zwraca ASC dla delta calc) --}}
                        @foreach (array_reverse($this->weights) as $w)
                            <tr class="border-t border-gray-200 dark:border-gray-800">
                                <td class="px-4 py-2 whitespace-nowrap">{{ $w->measuredAt->format('Y-m-d') }}</td>
                                <td class="px-4 py-2 text-right font-medium">{{ $this->formatWeight($w->weightKg) }}</td>
                                <td class="px-4 py-2 text-right {{ $this->deltaColorClass($w->deltaKg) }}">
                                    {{ $this->formatDelta($w->deltaKg) }}
                                </td>
                                <td class="px-4 py-2 text-right text-gray-600 dark:text-gray-400">
                                    {{ $w->girthCm !== null ? number_format($w->girthCm, 1, ',', ' ').' cm' : '—' }}
                                </td>
                                <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $w->notes ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Sekcja: Plan żywienia --}}
    <section class="space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold">{{ __('owner/horse_care.feeding.heading') }}</h2>
            <div class="text-xs text-gray-500">{{ __('owner/horse_care.feeding.note') }}</div>
        </div>

        @if (empty($this->feedingPlan))
            <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/40">
                {{ __('owner/horse_care.feeding.empty') }}
            </div>
        @else
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-4 py-2">{{ __('owner/horse_care.feeding.col.meal') }}</th>
                            <th class="px-4 py-2">{{ __('owner/horse_care.feeding.col.feed_type') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('owner/horse_care.feeding.col.amount') }}</th>
                            <th class="px-4 py-2">{{ __('owner/horse_care.feeding.col.notes') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->feedingPlan as $item)
                            <tr class="border-t border-gray-200 dark:border-gray-800">
                                <td class="px-4 py-2">
                                    <span class="inline-flex items-center rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
                                        {{ __('owner/horse_care.meal.'.$item->meal) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 font-medium">{{ $item->feedType }}</td>
                                <td class="px-4 py-2 text-right">{{ $item->amountFormatted }}</td>
                                <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $item->notes ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-filament-panels::page>
