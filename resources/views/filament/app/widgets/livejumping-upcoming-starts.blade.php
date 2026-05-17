@php
    /** @var \App\Filament\App\Widgets\LiveJumpingUpcomingStartsWidget $this */
    $starts = $this->getStarts();
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-trophy"
        :heading="__('app/dashboard.livejumping.heading')"
        :description="__('app/dashboard.livejumping.description')"
        collapsible
    >
        @if (count($starts) === 0)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('app/dashboard.livejumping.empty') }}
            </p>
        @else
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach (array_slice($starts, 0, 12) as $start)
                    @php
                        $when = \Illuminate\Support\Carbon::parse($start['starts_at'] ?? now());
                        $horseName = $start['horse']['name'] ?? null;
                        $riderName = $start['rider']['name'] ?? null;
                        $class = $start['class'] ?? '';
                        $compName = $start['competition_name'] ?? '';
                        $venue = $start['venue'] ?? '';
                    @endphp
                    <li class="py-2.5 flex items-start gap-3">
                        <div class="shrink-0 w-14 text-center">
                            <div class="text-[10px] uppercase font-semibold text-gray-500">{{ $when->isoFormat('MMM') }}</div>
                            <div class="text-lg font-bold leading-none text-gray-900 dark:text-gray-100">{{ $when->format('d') }}</div>
                            <div class="text-[11px] text-gray-500 mt-0.5">{{ $when->format('H:i') }}</div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">{{ $compName }}</div>
                            <div class="text-xs text-gray-500 truncate">{{ $venue }}</div>
                            <div class="mt-1 flex flex-wrap gap-1 text-[11px]">
                                @if ($class !== '')
                                    <span class="inline-flex items-center rounded bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 px-1.5 py-0.5 font-medium">{{ $class }}</span>
                                @endif
                                @if ($horseName)
                                    <span class="inline-flex items-center rounded bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-1.5 py-0.5">🐴 {{ $horseName }}</span>
                                @endif
                                @if ($riderName)
                                    <span class="inline-flex items-center rounded bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-1.5 py-0.5">👤 {{ $riderName }}</span>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        @if (count($starts) > 12)
            <div class="mt-2 text-xs text-gray-500">
                {{ __('app/dashboard.livejumping.more_count', ['count' => count($starts) - 12]) }}
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
