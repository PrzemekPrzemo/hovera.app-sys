<x-filament-widgets::widget>
    @php $slots = $this->getSlots(); @endphp
    @if (count($slots) > 0)
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 shadow-sm">
            <div class="mb-3">
                <div class="text-sm font-bold text-gray-900 dark:text-gray-100">
                    {{ __('transport/quick_start.heading') }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ __('transport/quick_start.subheading') }}
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                @foreach ($slots as $slot)
                    <a href="{{ $slot['url'] }}"
                       class="block rounded-lg border border-gray-200 dark:border-gray-700 px-4 py-3 hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/30 transition">
                        <div class="flex items-center gap-2 mb-1">
                            <x-dynamic-component :component="$slot['icon']" class="h-5 w-5 text-primary-600 dark:text-primary-300" />
                            <div class="font-semibold text-sm text-gray-900 dark:text-gray-100">{{ $slot['label'] }}</div>
                        </div>
                        <div class="text-xs text-gray-600 dark:text-gray-400 mb-2">
                            {{ $slot['body'] }}
                        </div>
                        <div class="text-xs font-semibold text-primary-600 dark:text-primary-400">
                            {{ $slot['cta'] }} →
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</x-filament-widgets::widget>
