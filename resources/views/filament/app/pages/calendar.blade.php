@php
    use App\Enums\CalendarEntryType;
@endphp

<x-filament-panels::page>
    {{-- Toolbar --}}
    <div class="flex flex-wrap items-end gap-3 mb-4">
        {{-- View mode toggle: Dzień / Tydzień / Miesiąc --}}
        <div class="flex items-center rounded-md bg-gray-100 dark:bg-gray-800 p-0.5">
            <button type="button" wire:click="setViewDay"
                    @class([
                        'px-3 py-1.5 text-sm font-medium rounded transition',
                        'bg-white dark:bg-gray-900 shadow-sm text-gray-900 dark:text-gray-100' => $viewMode === 'day',
                        'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100' => $viewMode !== 'day',
                    ])>{{ __('pages.calendar.view.day') }}</button>
            <button type="button" wire:click="setViewWeek"
                    @class([
                        'px-3 py-1.5 text-sm font-medium rounded transition',
                        'bg-white dark:bg-gray-900 shadow-sm text-gray-900 dark:text-gray-100' => $viewMode === 'week',
                        'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100' => $viewMode !== 'week',
                    ])>{{ __('pages.calendar.view.week') }}</button>
            <button type="button" wire:click="setViewMonth"
                    @class([
                        'px-3 py-1.5 text-sm font-medium rounded transition',
                        'bg-white dark:bg-gray-900 shadow-sm text-gray-900 dark:text-gray-100' => $viewMode === 'month',
                        'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100' => $viewMode !== 'month',
                    ])>{{ __('pages.calendar.view.month') }}</button>
        </div>

        {{-- Navigation per mode --}}
        <div class="flex items-center gap-1">
            @if ($viewMode === 'day')
                <x-filament::button color="gray" wire:click="previousDay" icon="heroicon-o-chevron-left" />
                <x-filament::button color="gray" wire:click="todayDay">{{ __('pages.calendar.today') }}</x-filament::button>
                <x-filament::button color="gray" wire:click="nextDay" icon="heroicon-o-chevron-right" />
            @elseif ($viewMode === 'week')
                <x-filament::button color="gray" wire:click="previousWeek" icon="heroicon-o-chevron-left" />
                <x-filament::button color="gray" wire:click="todayDay">{{ __('pages.calendar.today') }}</x-filament::button>
                <x-filament::button color="gray" wire:click="nextWeek" icon="heroicon-o-chevron-right" />
            @else
                <x-filament::button color="gray" wire:click="previousMonth" icon="heroicon-o-chevron-left" />
                <x-filament::button color="gray" wire:click="todayDay">{{ __('pages.calendar.today') }}</x-filament::button>
                <x-filament::button color="gray" wire:click="nextMonth" icon="heroicon-o-chevron-right" />
            @endif
        </div>

        <div class="flex flex-col">
            <label class="text-xs text-gray-500 mb-1">{{ __('pages.calendar.date') }}</label>
            <input type="date" wire:model.live="date"
                   class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm" />
        </div>

        @if ($viewMode === 'day')
            <div class="flex flex-col">
                <label class="text-xs text-gray-500 mb-1">{{ __('pages.calendar.group_by') }}</label>
                <select wire:model.live="groupBy"
                        class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm">
                    <option value="instructor">{{ __('pages.calendar.group.instructor') }}</option>
                    <option value="arena">{{ __('pages.calendar.group.arena') }}</option>
                    <option value="horse">{{ __('pages.calendar.group.horse') }}</option>
                    <option value="none">{{ __('pages.calendar.group.none') }}</option>
                </select>
            </div>
        @endif

        <div class="flex flex-col">
            <label class="text-xs text-gray-500 mb-1">{{ __('pages.calendar.type') }}</label>
            <select wire:model.live="typeFilter"
                    class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm">
                <option value="">{{ __('pages.calendar.type_all') }}</option>
                @foreach (CalendarEntryType::options() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="ml-auto">
            {{ $this->createEntryAction }}
        </div>
    </div>

    {{-- LiveJumping: pasek zawodów na najbliższe 7 dni. Tylko gdy włączone
         + LJ ma niepustą listę. --}}
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

    {{-- Body per view mode --}}
    @if ($viewMode === 'day')
        @include('filament.app.pages.calendar._day')
    @elseif ($viewMode === 'week')
        @include('filament.app.pages.calendar._week')
    @else
        @include('filament.app.pages.calendar._month')
    @endif

    {{-- Modals (rendered by Filament Actions) --}}
    <x-filament-actions::modals />
</x-filament-panels::page>
