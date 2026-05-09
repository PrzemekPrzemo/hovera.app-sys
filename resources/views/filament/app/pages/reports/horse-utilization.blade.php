@php
    /** @var \App\Filament\App\Pages\Reports\HorseUtilizationReport $this */
    $snapshot = $this->snapshot();
    $range = $snapshot['range'];
    $colorMap = [
        'success' => 'text-emerald-700 dark:text-emerald-300',
        'warning' => 'text-amber-700 dark:text-amber-300',
        'danger' => 'text-red-700 dark:text-red-300',
        'gray' => 'text-gray-500 dark:text-gray-400',
    ];
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <form method="get" class="flex items-end gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="month">
                        {{ __('pages.reports.month_picker') }}
                    </label>
                    <input type="month" name="month" id="month" value="{{ $range->key }}"
                           class="mt-1 rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                </div>
                <button type="submit"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                    {{ __('pages.reports.apply') }}
                </button>
            </form>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="mb-3 text-base font-semibold text-gray-900 dark:text-gray-100">
                {{ __('pages.reports.horse_utilization.heading', ['month' => $range->label()]) }}
            </h2>
            <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                {{ __('pages.reports.horse_utilization.subtitle') }}
            </p>
            @if ($snapshot['rows']->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('pages.reports.empty') }}</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="py-2 text-left font-medium text-gray-500 dark:text-gray-400">{{ __('pages.reports.horse_utilization.col_horse') }}</th>
                            <th class="py-2 text-right font-medium text-gray-500 dark:text-gray-400">{{ __('pages.reports.horse_utilization.col_lessons') }}</th>
                            <th class="py-2 text-right font-medium text-gray-500 dark:text-gray-400">{{ __('pages.reports.horse_utilization.col_hours') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($snapshot['rows'] as $row)
                        @php $cls = $colorMap[$this->colorForCount($row['lesson_count'])]; @endphp
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-2 text-gray-900 dark:text-gray-100">{{ $row['horse_name'] }}</td>
                            <td class="py-2 text-right font-bold {{ $cls }}">{{ $row['lesson_count'] }}</td>
                            <td class="py-2 text-right text-gray-700 dark:text-gray-300">{{ $row['hours'] }} h</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-filament-panels::page>
