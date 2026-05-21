<x-filament-panels::page>
    <form wire:submit="applyFilters" class="space-y-4">
        {{ $this->form }}
        <div class="flex justify-end gap-2">
            <x-filament::button type="button" wire:click="resetFilters" color="gray">
                {{ __('owner/horse_timeline.action.reset') }}
            </x-filament::button>
            <x-filament::button type="submit">
                {{ __('owner/horse_timeline.action.apply') }}
            </x-filament::button>
        </div>
    </form>

    @if (empty($entries))
        <div class="rounded-lg border border-dashed border-gray-200 p-8 text-center dark:border-gray-800">
            <div class="text-base font-semibold">{{ __('owner/horse_timeline.empty.heading') }}</div>
            <div class="mt-2 text-sm text-gray-500">{{ __('owner/horse_timeline.empty.description') }}</div>
        </div>
    @else
        <div class="space-y-4">
            <div class="text-sm text-gray-500">
                {{ __('owner/horse_timeline.summary', ['count' => count($entries)]) }}
            </div>

            <ol class="relative space-y-3 border-l border-gray-200 pl-6 dark:border-gray-800">
                @foreach ($entries as $entry)
                    @php($cls = $this->classesFor($entry->kind))
                    <li class="relative">
                        {{-- Ikona w timeline rail --}}
                        <span class="absolute -left-[37px] flex h-7 w-7 items-center justify-center rounded-full ring-4 ring-white dark:ring-gray-950 {{ $cls['ring_bg'] }}">
                            <x-filament::icon
                                :icon="$this->iconFor($entry->kind)"
                                class="h-4 w-4 {{ $cls['icon_text'] }}"
                            />
                        </span>

                        <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900/40">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $cls['badge_bg'] }} {{ $cls['badge_text'] }}">
                                            {{ __('owner/horse_timeline.kind.'.$entry->kind) }}
                                        </span>
                                        <span class="text-xs uppercase tracking-wide text-gray-500">
                                            {{ $this->subkindLabel($entry->kind, $entry->subkind) }}
                                        </span>
                                        @if ($entry->actorRole !== 'system')
                                            <span class="text-xs text-gray-400">·</span>
                                            <span class="text-xs text-gray-500">
                                                {{ __('owner/horse_timeline.actor.'.$entry->actorRole) }}
                                                @if ($entry->actorName)
                                                    · {{ $entry->actorName }}
                                                @endif
                                            </span>
                                        @endif
                                    </div>
                                    <div class="mt-1 font-medium">{{ $entry->title }}</div>
                                    @if ($entry->description)
                                        <div class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $entry->description }}</div>
                                    @endif
                                    @if ($entry->kind === 'health' && ! empty($entry->payload['next_due_at']))
                                        <div class="mt-1 text-xs text-amber-700 dark:text-amber-300">
                                            {{ __('owner/horse_timeline.next_due_at') }}: {{ $entry->payload['next_due_at'] }}
                                        </div>
                                    @endif
                                </div>
                                <div class="shrink-0 text-right">
                                    <div class="text-xs font-medium">{{ $entry->occurredAt->format('Y-m-d') }}</div>
                                    <div class="text-xs text-gray-500">{{ $entry->occurredAt->format('H:i') }}</div>
                                    @if ($entry->costCents !== null && $entry->costCents > 0)
                                        <div class="mt-1 text-sm font-medium">{{ $this->formatCents($entry->costCents) }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif
</x-filament-panels::page>
