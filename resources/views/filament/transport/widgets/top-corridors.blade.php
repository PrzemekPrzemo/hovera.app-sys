<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">{{ __('transport/dashboard.top_corridors.heading') }}</x-slot>
        <x-slot name="description">{{ __('transport/dashboard.top_corridors.description') }}</x-slot>

        @if (count($corridors) === 0)
            <p class="text-sm text-gray-500">{{ __('transport/dashboard.top_corridors.empty') }}</p>
        @else
            <div class="space-y-1.5">
                @foreach ($corridors as $row)
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <div class="flex-1 min-w-0 truncate">
                                <span class="font-medium">{{ $row['from'] }}</span>
                                <span class="text-gray-400 mx-1">→</span>
                                <span class="font-medium">{{ $row['to'] }}</span>
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
