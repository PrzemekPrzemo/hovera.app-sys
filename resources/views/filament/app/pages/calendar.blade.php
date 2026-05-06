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
            {{ ($this->createEntryAction)() }}
        </div>
    </div>

    {{-- Date heading --}}
    <h2 class="text-lg font-semibold mb-3">
        {{ \Illuminate\Support\Carbon::parse($timetable['date'])->translatedFormat('l, d F Y') }}
    </h2>

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
