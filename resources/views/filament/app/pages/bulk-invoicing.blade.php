@php
    /** @var \App\Filament\App\Pages\BulkInvoicing $this */
    $preview = $this->preview();
    $totalNet = array_sum(array_column($preview, 'net_cents'));
    $totalGross = array_sum(array_column($preview, 'total_cents'));
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <form method="get" class="flex items-end gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="month">
                        {{ __('pages.bulk_invoicing.month_picker') }}
                    </label>
                    <input type="month" name="month" id="month"
                           value="{{ $this->monthStart()->format('Y-m') }}"
                           class="mt-1 rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                </div>
                <button type="submit"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                    {{ __('pages.bulk_invoicing.refresh') }}
                </button>
            </form>
            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                {{ __('pages.bulk_invoicing.helper') }}
            </p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="mb-3 text-base font-semibold text-gray-900 dark:text-gray-100">
                {{ __('pages.bulk_invoicing.preview_heading', ['month' => $this->monthLabel(), 'count' => count($preview)]) }}
            </h2>

            @if (empty($preview))
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('pages.bulk_invoicing.empty') }}
                </p>
            @else
                <div class="space-y-4">
                    @foreach ($preview as $row)
                        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <div class="mb-2 flex items-baseline justify-between">
                                <label class="flex items-center gap-2 text-base font-semibold text-gray-900 dark:text-gray-100">
                                    <input type="checkbox"
                                           wire:model="selected"
                                           value="{{ $row['client_id'] }}"
                                           class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                    {{ $row['client_name'] }}
                                </label>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ count($row['items']) }} {{ __('pages.bulk_invoicing.items_suffix') }} ·
                                    <span class="font-bold text-gray-900 dark:text-gray-100">{{ $this->formatCents($row['total_cents']) }}</span>
                                </div>
                            </div>
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <th class="py-1 text-left font-medium text-gray-500 dark:text-gray-400">{{ __('pages.bulk_invoicing.col_item') }}</th>
                                        <th class="py-1 text-right font-medium text-gray-500 dark:text-gray-400">{{ __('pages.bulk_invoicing.col_qty') }}</th>
                                        <th class="py-1 text-right font-medium text-gray-500 dark:text-gray-400">{{ __('pages.bulk_invoicing.col_unit_price') }}</th>
                                        <th class="py-1 text-right font-medium text-gray-500 dark:text-gray-400">{{ __('pages.bulk_invoicing.col_net') }}</th>
                                        <th class="py-1 text-right font-medium text-gray-500 dark:text-gray-400">{{ __('pages.bulk_invoicing.col_gross') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach ($row['items'] as $item)
                                    <tr class="border-b border-gray-50 dark:border-gray-800/50">
                                        <td class="py-1 text-gray-900 dark:text-gray-100">{{ $item['name'] }}</td>
                                        <td class="py-1 text-right text-gray-700 dark:text-gray-300">
                                            {{ rtrim(rtrim(number_format($item['quantity'], 2, ',', ''), '0'), ',') }} {{ $item['unit'] }}
                                        </td>
                                        <td class="py-1 text-right text-gray-700 dark:text-gray-300">{{ $this->formatCents($item['unit_price_cents']) }}</td>
                                        <td class="py-1 text-right text-gray-700 dark:text-gray-300">{{ $this->formatCents($item['net_cents']) }}</td>
                                        <td class="py-1 text-right font-semibold text-gray-900 dark:text-gray-100">{{ $this->formatCents($item['total_cents']) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 flex items-baseline justify-between border-t border-gray-200 pt-4 dark:border-gray-700">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('pages.bulk_invoicing.totals') }}</span>
                    <span class="text-lg font-bold text-primary-600 dark:text-primary-400">
                        {{ $this->formatCents($totalNet) }} {{ __('pages.bulk_invoicing.net_short') }} ·
                        {{ $this->formatCents($totalGross) }} {{ __('pages.bulk_invoicing.gross_short') }}
                    </span>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
