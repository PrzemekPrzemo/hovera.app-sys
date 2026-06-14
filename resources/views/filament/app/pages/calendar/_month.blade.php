@php
    $month = $this->getMonthTimetable();
    $dayLabels = ['pn', 'wt', 'śr', 'czw', 'pt', 'sb', 'nd'];
@endphp

<h2 class="text-lg font-semibold mb-3 capitalize">
    {{ $month['month_label'] }}
</h2>

<div class="bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
    {{-- Nagłówek z dniami tygodnia --}}
    <div class="grid grid-cols-7 border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900">
        @foreach ($dayLabels as $label)
            <div class="px-2 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 text-center">
                {{ $label }}
            </div>
        @endforeach
    </div>

    {{-- Grid dni --}}
    <div class="grid grid-cols-7">
        @foreach ($month['days'] as $day)
            <button type="button"
                    wire:click="jumpToDay('{{ $day['date'] }}')"
                    @class([
                        'block text-left p-2 min-h-[100px] border-r border-b border-gray-200 dark:border-gray-800 transition hover:bg-gray-50 dark:hover:bg-gray-800/40',
                        'bg-gray-50/50 dark:bg-gray-900/40' => ! $day['in_month'],
                        'bg-amber-50/40 dark:bg-amber-900/10' => $day['is_today'],
                    ])>
                <div @class([
                    'text-xs font-semibold mb-1',
                    'text-gray-400' => ! $day['in_month'],
                    'text-amber-700 dark:text-amber-300' => $day['is_today'],
                    'text-gray-700 dark:text-gray-300' => $day['in_month'] && ! $day['is_today'],
                ])>
                    {{ $day['day_of_month'] }}
                </div>

                <div class="space-y-0.5">
                    @foreach ($day['entries'] as $entry)
                        <div class="text-[10px] leading-tight px-1.5 py-0.5 rounded text-white truncate"
                             style="background: {{ $entry['color'] }};">
                            <span class="font-semibold">{{ $entry['starts_at_display'] }}</span>
                            {{ $entry['title'] }}
                        </div>
                    @endforeach
                    @if ($day['total_count'] > 3)
                        <div class="text-[10px] text-gray-500 px-1">
                            +{{ $day['total_count'] - 3 }} {{ __('pages.calendar.entries_short') }}
                        </div>
                    @endif
                </div>
            </button>
        @endforeach
    </div>
</div>
