<x-filament-widgets::widget>
    <x-filament::section
        :heading="__('owner/transport.widget.upcoming.heading')"
        :description="__('owner/transport.widget.upcoming.description')"
        icon="heroicon-o-truck"
    >
        @if ($orders->isEmpty())
            <div class="py-8 text-center">
                <p class="mb-4 text-sm text-gray-500">
                    {{ __('owner/transport.widget.upcoming.empty') }}
                </p>
                <x-filament::button
                    :href="route('filament.owner.pages.order-transport')"
                    tag="a"
                    icon="heroicon-o-plus"
                >
                    {{ __('owner/transport.widget.upcoming.cta') }}
                </x-filament::button>
            </div>
        @else
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($orders as $order)
                    <a
                        href="{{ \App\Filament\Owner\Resources\TransportOrderResource::getUrl('view', ['record' => $order->id]) }}"
                        class="flex items-center justify-between gap-4 py-3 transition hover:bg-gray-50 dark:hover:bg-gray-800"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $order->pickup_address }}
                                <span class="text-gray-400">→</span>
                                {{ $order->dropoff_address }}
                            </div>
                            <div class="mt-1 text-xs text-gray-500">
                                {{ $order->preferred_date?->format('d.m.Y') }}
                                @if ($order->horse?->name)
                                    · {{ $order->horse->name }}
                                @endif
                            </div>
                        </div>
                        <span
                            @class([
                                'shrink-0 rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200' => $order->status === 'open',
                                'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200' => $order->status === 'quoted',
                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' => $order->status === 'accepted',
                            ])
                        >
                            {{ \App\Filament\Owner\Resources\TransportOrderResource::statusOptions()[$order->status] ?? $order->status }}
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
