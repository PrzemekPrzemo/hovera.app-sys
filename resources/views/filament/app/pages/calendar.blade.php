@php
    use App\Enums\CalendarEntryStatus;
    use App\Enums\CalendarEntryType;

    $timetable = $this->getTimetable();
    $minute = \App\Services\Calendar\TimetableLoader::MINUTE_HEIGHT_PX;

    // Hour labels rendered down the left gutter
    $hourLabels = [];
    $cursor = $timetable['view_start']->copy();
    while ($cursor->lte($timetable['view_end'])) {
        $hourLabels[] = $cursor->format('H:i');
        $cursor->addHour();
    }
@endphp

<x-filament-panels::page>
    {{-- Toolbar --}}
    <div class="flex flex-wrap items-end gap-3 mb-4">
        <div class="flex items-center gap-1">
            <x-filament::button color="gray" wire:click="previousDay" icon="heroicon-o-chevron-left" />
            <x-filament::button color="gray" wire:click="todayDay">Dziś</x-filament::button>
            <x-filament::button color="gray" wire:click="nextDay" icon="heroicon-o-chevron-right" />
        </div>

        <div class="flex flex-col">
            <label class="text-xs text-gray-500 mb-1">Data</label>
            <input type="date" wire:model.live="date"
                   class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm" />
        </div>

        <div class="flex flex-col">
            <label class="text-xs text-gray-500 mb-1">Grupuj</label>
            <select wire:model.live="groupBy"
                    class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm">
                <option value="instructor">wg instruktora</option>
                <option value="arena">wg ujeżdżalni</option>
                <option value="horse">wg konia</option>
                <option value="none">brak</option>
            </select>
        </div>

        <div class="flex flex-col">
            <label class="text-xs text-gray-500 mb-1">Typ</label>
            <select wire:model.live="typeFilter"
                    class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm">
                <option value="">Wszystkie</option>
                @foreach (CalendarEntryType::options() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="ml-auto">
            {{ $this->createEntryAction }}
        </div>
    </div>

    {{-- Date heading --}}
    <h2 class="text-lg font-semibold mb-3">
        {{ \Illuminate\Support\Carbon::parse($timetable['date'])->translatedFormat('l, d F Y') }}
    </h2>

    {{-- LiveJumping: pasek zawodów na najbliższe 7 dni. Renderowany TYLKO
         gdy master admin włączył partnership w /admin/live-jumping-settings
         i LJ zwrócił niepustą listę. --}}
    @php $ljEvents = $this->getLiveJumpingEvents(); @endphp
    @if (count($ljEvents) > 0)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 dark:border-amber-900/50 dark:bg-amber-900/20 px-3 py-2">
            <div class="flex items-center gap-2 mb-1.5">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 text-amber-700 dark:text-amber-400"><path d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0"/></svg>
                <span class="text-sm font-semibold text-amber-900 dark:text-amber-200">{{ __('pages.calendar.livejumping.heading') }}</span>
            </div>
            <div class="flex flex-wrap gap-1.5">
                @foreach (array_slice($ljEvents, 0, 8) as $ev)
                    @php
                        $from = \Illuminate\Support\Carbon::parse($ev['starts_on'] ?? now());
                    @endphp
                    <span class="inline-flex items-center gap-1 rounded-full bg-white dark:bg-amber-900/40 border border-amber-300 dark:border-amber-800 px-2 py-0.5 text-xs text-amber-900 dark:text-amber-200">
                        <span class="font-semibold">{{ $from->format('d.m') }}</span>
                        <span class="truncate max-w-[14rem]">{{ $ev['name'] ?? '' }}</span>
                        @if (!empty($ev['level']))
                            <span class="opacity-70">· {{ $ev['level'] }}</span>
                        @endif
                    </span>
                @endforeach
                @if (count($ljEvents) > 8)
                    <span class="inline-flex items-center text-xs text-amber-700 dark:text-amber-400">
                        +{{ count($ljEvents) - 8 }} {{ __('pages.calendar.livejumping.more') }}
                    </span>
                @endif
            </div>
        </div>
    @endif

    {{-- Grid --}}
    <div class="bg-white dark:bg-gray-900 rounded-lg shadow overflow-x-auto">
        <div class="flex">
            {{-- Hour gutter --}}
            <div class="flex-none w-16 border-r border-gray-200 dark:border-gray-800 sticky left-0 bg-white dark:bg-gray-900 z-10">
                <div class="h-10 border-b border-gray-200 dark:border-gray-800"></div>
                <div class="relative" style="height: {{ $timetable['view_minutes'] * $minute }}px;">
                    @foreach ($hourLabels as $i => $label)
                        <div class="absolute right-2 -translate-y-2 text-xs text-gray-400"
                             style="top: {{ $i * 60 * $minute }}px;">{{ $label }}</div>
                    @endforeach
                </div>
            </div>

            {{-- Lanes --}}
            <div class="flex-1 flex">
                @forelse ($timetable['lanes'] as $lane)
                    <div class="flex-1 min-w-[180px] border-r border-gray-200 dark:border-gray-800">
                        {{-- Lane header --}}
                        <div class="h-10 px-3 border-b border-gray-200 dark:border-gray-800 flex items-center gap-2 sticky top-0 bg-white dark:bg-gray-900 z-10"
                             @if ($lane['color']) style="border-bottom-color: {{ $lane['color'] }}; border-bottom-width: 2px;" @endif>
                            @if ($lane['color'])
                                <span class="w-2 h-2 rounded-full" style="background: {{ $lane['color'] }}"></span>
                            @endif
                            <span class="font-medium text-sm truncate">{{ $lane['label'] }}</span>
                            <span class="ml-auto text-xs text-gray-400">{{ count($lane['entries']) }}</span>
                        </div>

                        {{-- Time canvas --}}
                        <div class="relative bg-gray-50 dark:bg-gray-800/40"
                             style="height: {{ $timetable['view_minutes'] * $minute }}px;">

                            {{-- Hour grid lines --}}
                            @for ($h = 0; $h <= count($hourLabels) - 1; $h++)
                                <div class="absolute left-0 right-0 border-t border-gray-200 dark:border-gray-800"
                                     style="top: {{ $h * 60 * $minute }}px;"></div>
                            @endfor

                            {{-- Half-hour subtle lines --}}
                            @for ($h = 0; $h <= count($hourLabels) - 1; $h++)
                                <div class="absolute left-0 right-0 border-t border-dashed border-gray-100 dark:border-gray-800/60"
                                     style="top: {{ ($h * 60 + 30) * $minute }}px;"></div>
                            @endfor

                            {{-- Entries --}}
                            @foreach ($lane['entries'] as $entry)
                                <button type="button"
                                        wire:click="mountAction('editEntry', {{ json_encode(['entry_id' => $entry['id']]) }})"
                                        class="absolute left-1 right-1 rounded px-2 py-1 text-left text-white text-xs shadow hover:brightness-110 focus:outline focus:outline-2 focus:outline-offset-1 transition"
                                        style="top: {{ $entry['top_px'] }}px; height: {{ $entry['height_px'] }}px; background: {{ $entry['color'] }}; opacity: {{ $entry['status']->value === 'cancelled' ? '0.55' : '1' }};">
                                    <div class="font-semibold leading-tight truncate">
                                        {{ $entry['starts_at_display'] }}–{{ $entry['ends_at_display'] }}
                                    </div>
                                    <div class="leading-tight truncate">
                                        @if ($entry['title'])
                                            {{ $entry['title'] }}
                                        @else
                                            {{ $entry['horse'] ?? $entry['type_label'] }}
                                        @endif
                                    </div>
                                    @if ($entry['height_px'] >= 50)
                                        <div class="leading-tight truncate opacity-90">
                                            @if ($entry['client']) {{ $entry['client'] }} @endif
                                            @if ($entry['instructor']) · {{ $entry['instructor'] }} @endif
                                        </div>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="flex-1 p-6 text-sm text-gray-500">Brak zdefiniowanych zasobów.</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Modals (rendered by Filament Actions) --}}
    <x-filament-actions::modals />
</x-filament-panels::page>
