<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">{{ __('transport/dashboard.routes_heatmap.heading') }}</x-slot>
        <x-slot name="description">{{ __('transport/dashboard.routes_heatmap.description') }}</x-slot>

        @if (count($pairs) === 0)
            <div class="py-6 text-center">
                <x-filament::icon
                    icon="heroicon-o-map"
                    class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600"
                />
                <p class="mt-2 text-sm text-gray-500">{{ __('transport/dashboard.routes_heatmap.empty') }}</p>
            </div>
        @else
            <div class="space-y-1.5">
                @foreach ($pairs as $row)
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <div class="flex-1 min-w-0 truncate">
                                <span class="font-medium capitalize">{{ $row['from'] }}</span>
                                <span class="text-gray-400 mx-1">&rarr;</span>
                                <span class="font-medium capitalize">{{ $row['to'] }}</span>
                            </div>
                            <div class="text-xs text-gray-500 ml-3 flex-shrink-0 tabular-nums">
                                {{ $row['count'] }} · {{ number_format($row['share'], 1, ',', '') }}%
                            </div>
                        </div>
                        <div class="mt-1 h-1.5 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                            <div class="h-full bg-primary-500" style="width: {{ min(100, max(2, $row['share'])) }}%;"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
