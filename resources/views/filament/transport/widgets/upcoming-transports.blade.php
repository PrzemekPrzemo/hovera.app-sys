<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">{{ __('transport/dashboard.upcoming.heading') }}</x-slot>
        <x-slot name="description">{{ __('transport/dashboard.upcoming.description') }}</x-slot>

        <div class="grid gap-4 sm:grid-cols-2">
            @foreach (['today' => 'today', 'tomorrow' => 'tomorrow'] as $key => $label)
                @php $items = $$key; @endphp
                <div>
                    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">
                        {{ __('transport/dashboard.upcoming.'.$label) }}
                        <span class="ml-1 text-xs font-normal text-gray-400">({{ $items->count() }})</span>
                    </h3>
                    @if ($items->isEmpty())
                        <p class="text-xs text-gray-500 italic">{{ __('transport/dashboard.upcoming.empty') }}</p>
                    @else
                        <ul class="space-y-1.5">
                            @foreach ($items as $quote)
                                <li class="rounded border border-gray-200 dark:border-gray-700 p-2 text-xs">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="font-mono text-gray-500">{{ $quote->number }}</div>
                                        @if ($quote->preferred_time)
                                            <div class="font-semibold tabular-nums">{{ $quote->preferred_time }}</div>
                                        @endif
                                    </div>
                                    <div class="mt-1 truncate">
                                        <span class="font-medium">{{ $quote->customer_name }}</span>
                                    </div>
                                    <div class="text-gray-500 truncate">
                                        {{ $quote->pickup_address }} → {{ $quote->dropoff_address }}
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
