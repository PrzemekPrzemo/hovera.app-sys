@php
    /** @var \App\Filament\App\Pages\MyTasks $this */
    $specialist = $this->specialist();
    $overdue = $this->overdue();
    $upcoming = $this->upcoming();
    $recent = $this->recent();
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        @if ($specialist)
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('pages.my_tasks.signed_in_as') }}
                </div>
                <div class="mt-1 text-base font-semibold text-gray-900 dark:text-gray-100">
                    {{ $specialist->name }}
                    <span class="ms-2 inline-flex items-center rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
                        {{ $specialist->isVet() ? __('app/specialist.types.vet') : __('app/specialist.types.farrier') }}
                    </span>
                </div>
            </div>
        @endif

        {{-- Przeterminowane --}}
        <section class="rounded-xl border border-red-200 bg-white p-4 dark:border-red-900 dark:bg-gray-900">
            <h2 class="mb-3 flex items-center gap-2 text-base font-semibold text-red-700 dark:text-red-400">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5"/>
                {{ __('pages.my_tasks.sections.overdue') }}
                @if ($overdue->isNotEmpty())
                    <span class="ms-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-900/40 dark:text-red-300">
                        {{ $overdue->count() }}
                    </span>
                @endif
            </h2>
            @if ($overdue->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('pages.my_tasks.empty.overdue') }}</p>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($overdue as $hr)
                        @php $days = abs((int) $this->daysFromNow($hr->next_due_at)); @endphp
                        <li class="flex items-center justify-between gap-4 py-2.5">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 text-sm">
                                    <strong class="font-semibold text-gray-900 dark:text-gray-100">{{ $hr->horse?->name ?? '—' }}</strong>
                                    <span class="text-gray-500 dark:text-gray-400">·</span>
                                    <span class="text-gray-700 dark:text-gray-300">{{ $hr->type->label() }}</span>
                                </div>
                                @if ($hr->summary)
                                    <div class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $hr->summary }}</div>
                                @endif
                            </div>
                            <div class="shrink-0 text-right text-sm">
                                <div class="font-medium text-red-700 dark:text-red-300">{{ $hr->next_due_at?->format('d.m.Y') }}</div>
                                <div class="text-xs text-red-600 dark:text-red-400">
                                    {{ trans_choice('pages.my_tasks.overdue_by_days', $days, ['days' => $days]) }}
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Najbliższe zabiegi --}}
        <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="mb-3 flex items-center gap-2 text-base font-semibold text-gray-900 dark:text-gray-100">
                <x-filament::icon icon="heroicon-o-calendar-days" class="h-5 w-5 text-primary-600"/>
                {{ __('pages.my_tasks.sections.upcoming') }}
                @if ($upcoming->isNotEmpty())
                    <span class="ms-1 rounded-full bg-primary-50 px-2 py-0.5 text-xs font-semibold text-primary-700 dark:bg-primary-900/40 dark:text-primary-300">
                        {{ $upcoming->count() }}
                    </span>
                @endif
            </h2>
            @if ($upcoming->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('pages.my_tasks.empty.upcoming') }}</p>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($upcoming as $hr)
                        @php $days = (int) $this->daysFromNow($hr->next_due_at); @endphp
                        <li class="flex items-center justify-between gap-4 py-2.5">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 text-sm">
                                    <strong class="font-semibold text-gray-900 dark:text-gray-100">{{ $hr->horse?->name ?? '—' }}</strong>
                                    <span class="text-gray-500 dark:text-gray-400">·</span>
                                    <span class="text-gray-700 dark:text-gray-300">{{ $hr->type->label() }}</span>
                                </div>
                                @if ($hr->summary)
                                    <div class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $hr->summary }}</div>
                                @endif
                            </div>
                            <div class="shrink-0 text-right text-sm">
                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $hr->next_due_at?->format('d.m.Y') }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ trans_choice('pages.my_tasks.in_days', $days, ['days' => $days]) }}
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Ostatnio wykonane --}}
        <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="mb-3 flex items-center gap-2 text-base font-semibold text-gray-900 dark:text-gray-100">
                <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5 text-green-600"/>
                {{ __('pages.my_tasks.sections.recent') }}
                @if ($recent->isNotEmpty())
                    <span class="ms-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                        {{ $recent->count() }}
                    </span>
                @endif
            </h2>
            @if ($recent->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('pages.my_tasks.empty.recent') }}</p>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($recent as $hr)
                        <li class="flex items-center justify-between gap-4 py-2.5">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 text-sm">
                                    <strong class="font-semibold text-gray-900 dark:text-gray-100">{{ $hr->horse?->name ?? '—' }}</strong>
                                    <span class="text-gray-500 dark:text-gray-400">·</span>
                                    <span class="text-gray-700 dark:text-gray-300">{{ $hr->type->label() }}</span>
                                </div>
                                @if ($hr->summary)
                                    <div class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $hr->summary }}</div>
                                @endif
                            </div>
                            <div class="shrink-0 text-right text-sm text-gray-500 dark:text-gray-400">
                                {{ $hr->performed_at?->format('d.m.Y') }}
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-filament-panels::page>
