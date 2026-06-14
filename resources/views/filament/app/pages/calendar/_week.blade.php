@php
    $week = $this->getWeekTimetable();
@endphp

<h2 class="text-lg font-semibold mb-3">
    {{ $week['week_start']->translatedFormat('d.m') }} – {{ $week['week_end']->translatedFormat('d.m.Y') }}
</h2>

<div class="bg-white dark:bg-gray-900 rounded-lg shadow overflow-x-auto">
    <div class="grid grid-cols-7 min-w-[840px]">
        @foreach ($week['days'] as $day)
            <div class="border-r last:border-r-0 border-gray-200 dark:border-gray-800 flex flex-col">
                {{-- Header dnia - klik przełącza na widok dzienny --}}
                <button type="button"
                        wire:click="jumpToDay('{{ $day['date'] }}')"
                        class="px-3 py-2 border-b border-gray-200 dark:border-gray-800 text-left hover:bg-gray-50 dark:hover:bg-gray-800/50 transition"
                        @class([
                            'bg-amber-50 dark:bg-amber-900/20' => $day['is_today'],
                        ])>
                    <div class="text-xs font-semibold uppercase tracking-wide
                                {{ $day['is_today'] ? 'text-amber-700 dark:text-amber-300' : 'text-gray-500' }}">
                        {{ $day['label'] }}
                    </div>
                    <div class="text-xs text-gray-400 mt-0.5">
                        {{ count($day['entries']) }} {{ __('pages.calendar.entries_short') }}
                    </div>
                </button>

                {{-- Lista wpisów (max wszystkie, ale chip jest mały) --}}
                <div class="flex-1 p-2 space-y-1 min-h-[300px] bg-gray-50/50 dark:bg-gray-900/40">
                    @forelse ($day['entries'] as $entry)
                        <button type="button"
                                wire:click="mountAction('editEntry', {{ json_encode(['entry_id' => $entry['id']]) }})"
                                class="block w-full text-left rounded px-2 py-1 text-xs text-white hover:brightness-110 transition"
                                style="background: {{ $entry['color'] }}; opacity: {{ $entry['status']->value === 'cancelled' ? '0.55' : '1' }};">
                            <div class="font-semibold leading-tight">
                                {{ $entry['starts_at_display'] }}
                            </div>
                            <div class="leading-tight truncate">
                                @if ($entry['title'])
                                    {{ $entry['title'] }}
                                @else
                                    {{ $entry['horse'] ?? $entry['type_label'] }}
                                @endif
                            </div>
                        </button>
                    @empty
                        <div class="text-xs text-gray-400 px-1 py-2">—</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>
