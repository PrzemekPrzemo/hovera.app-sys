@php
    /** @var \App\Filament\App\Pages\Reports\ReceivablesAgingReport $this */
    $snapshot = $this->snapshot();
    $totals = $snapshot['totals'];
    $bucketLabels = [
        '0_30' => __('pages.reports.aging.bucket.0_30'),
        '31_60' => __('pages.reports.aging.bucket.31_60'),
        '61_90' => __('pages.reports.aging.bucket.61_90'),
        '90_plus' => __('pages.reports.aging.bucket.90_plus'),
    ];
    $bucketBg = [
        '0_30' => 'bg-amber-50 dark:bg-amber-900/30',
        '31_60' => 'bg-orange-50 dark:bg-orange-900/30',
        '61_90' => 'bg-rose-50 dark:bg-rose-900/30',
        '90_plus' => 'bg-red-50 dark:bg-red-900/30',
    ];
    $bucketText = [
        '0_30' => 'text-amber-700 dark:text-amber-300',
        '31_60' => 'text-orange-700 dark:text-orange-300',
        '61_90' => 'text-rose-700 dark:text-rose-300',
        '90_plus' => 'text-red-700 dark:text-red-300',
    ];
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (['0_30', '31_60', '61_90', '90_plus'] as $bucket)
                <div class="rounded-xl border border-gray-200 p-4 {{ $bucketBg[$bucket] }}">
                    <div class="text-xs font-semibold uppercase tracking-wide {{ $bucketText[$bucket] }}">
                        {{ $bucketLabels[$bucket] }}
                    </div>
                    <div class="mt-2 text-xl font-bold {{ $bucketText[$bucket] }}">
                        {{ $this->formatCents($totals[$bucket]) }}
                    </div>
                </div>
            @endforeach
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-baseline justify-between">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                    {{ __('pages.reports.aging.total_heading') }}
                </h2>
                <div class="text-2xl font-bold text-rose-600 dark:text-rose-400">
                    {{ $this->formatCents($totals['total']) }}
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="mb-3 text-base font-semibold text-gray-900 dark:text-gray-100">
                {{ __('pages.reports.aging.list_heading') }}
            </h2>
            @if ($snapshot['rows']->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('pages.reports.aging.empty') }}</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="py-2 text-left font-medium text-gray-500 dark:text-gray-400">{{ __('pages.reports.aging.col_invoice') }}</th>
                            <th class="py-2 text-left font-medium text-gray-500 dark:text-gray-400">{{ __('pages.reports.aging.col_client') }}</th>
                            <th class="py-2 text-left font-medium text-gray-500 dark:text-gray-400">{{ __('pages.reports.aging.col_due_at') }}</th>
                            <th class="py-2 text-right font-medium text-gray-500 dark:text-gray-400">{{ __('pages.reports.aging.col_days_overdue') }}</th>
                            <th class="py-2 text-right font-medium text-gray-500 dark:text-gray-400">{{ __('pages.reports.aging.col_amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($snapshot['rows'] as $row)
                        @php $i = $row['invoice']; @endphp
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-2 font-mono text-gray-900 dark:text-gray-100">{{ $i->number ?? '—' }}</td>
                            <td class="py-2 text-gray-700 dark:text-gray-300">{{ $i->client?->name ?? '—' }}</td>
                            <td class="py-2 text-gray-700 dark:text-gray-300">{{ $i->due_at?->format('Y-m-d') }}</td>
                            <td class="py-2 text-right font-semibold {{ $bucketText[$row['bucket']] }}">
                                {{ $row['days_overdue'] }} {{ __('pages.reports.aging.days') }}
                            </td>
                            <td class="py-2 text-right font-semibold text-gray-900 dark:text-gray-100">
                                {{ $this->formatCents((int) $i->total_cents) }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-filament-panels::page>
