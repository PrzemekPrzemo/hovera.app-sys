@php
    /** @var \App\Filament\App\Pages\Reports\RevenueReport $this */
    $snapshot = $this->snapshot();
    $range = $snapshot['range'];
    $bucketLabels = [
        'boarding' => __('pages.reports.revenue.bucket.boarding'),
        'lessons' => __('pages.reports.revenue.bucket.lessons'),
        'passes' => __('pages.reports.revenue.bucket.passes'),
        'other' => __('pages.reports.revenue.bucket.other'),
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

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($snapshot['buckets'] as $bucket => $cents)
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ $bucketLabels[$bucket] }}
                    </div>
                    <div class="mt-2 text-xl font-bold text-gray-900 dark:text-gray-100">
                        {{ $this->formatCents($cents) }}
                    </div>
                </div>
            @endforeach
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-baseline justify-between">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                    {{ __('pages.reports.revenue.total_heading', ['month' => $range->label()]) }}
                </h2>
                <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                    {{ $this->formatCents($snapshot['total_cents']) }}
                </div>
            </div>
            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('pages.reports.revenue.invoice_count', ['count' => $snapshot['invoice_count']]) }}
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="mb-3 text-base font-semibold text-gray-900 dark:text-gray-100">
                {{ __('pages.reports.revenue.top_items') }}
            </h2>
            @if (empty($snapshot['top_items']))
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('pages.reports.empty') }}</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="py-2 text-left font-medium text-gray-500 dark:text-gray-400">{{ __('pages.reports.col_item') }}</th>
                            <th class="py-2 text-right font-medium text-gray-500 dark:text-gray-400">{{ __('pages.reports.col_total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($snapshot['top_items'] as $row)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-2 text-gray-900 dark:text-gray-100">{{ $row['name'] }}</td>
                            <td class="py-2 text-right font-semibold text-gray-900 dark:text-gray-100">
                                {{ $this->formatCents($row['total_cents']) }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-filament-panels::page>
