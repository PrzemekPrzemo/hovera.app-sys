<x-filament-panels::page>
    <div class="space-y-4">
        <div class="text-sm text-gray-600 dark:text-gray-400">
            @if ($this->activeHorseName())
                {{ __('owner/invoices.list.description_filtered', ['horse' => $this->activeHorseName()]) }}
            @else
                {{ __('owner/invoices.list.description') }}
            @endif
        </div>

        @if (! empty($this->horseOptions))
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs uppercase tracking-wide text-gray-500">
                    {{ __('owner/invoices.list.filter.label') }}
                </span>
                <a
                    href="{{ $this->filterUrl(null) }}"
                    @class([
                        'inline-flex items-center rounded-full px-3 py-1 text-xs font-medium transition',
                        'bg-primary-600 text-white' => $this->horseFilter === null,
                        'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' => $this->horseFilter !== null,
                    ])
                >
                    {{ __('owner/invoices.list.filter.all') }}
                </a>
                @foreach ($this->horseOptions as $id => $name)
                    <a
                        href="{{ $this->filterUrl($id) }}"
                        @class([
                            'inline-flex items-center rounded-full px-3 py-1 text-xs font-medium transition',
                            'bg-primary-600 text-white' => $this->horseFilter === $id,
                            'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' => $this->horseFilter !== $id,
                        ])
                    >
                        {{ $name }}
                    </a>
                @endforeach
            </div>
        @endif

        @if ($this->invoices->isEmpty())
            <div class="rounded-lg border border-dashed border-gray-200 p-8 text-center dark:border-gray-800">
                <div class="text-base font-semibold">
                    {{ __('owner/invoices.list.empty_heading') }}
                </div>
                <div class="mt-2 text-sm text-gray-500">
                    {{ __('owner/invoices.list.empty_description') }}
                </div>
            </div>
        @else
            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-2">{{ __('owner/invoices.table.number') }}</th>
                            <th class="px-4 py-2">{{ __('owner/invoices.table.stable') }}</th>
                            <th class="px-4 py-2">{{ __('owner/invoices.table.horse') }}</th>
                            <th class="px-4 py-2">{{ __('owner/invoices.table.issued_at') }}</th>
                            <th class="px-4 py-2">{{ __('owner/invoices.table.due_at') }}</th>
                            <th class="px-4 py-2">{{ __('owner/invoices.table.status') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('owner/invoices.table.total') }}</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($this->invoices as $invoice)
                            <tr>
                                <td class="px-4 py-2 font-mono text-xs">{{ $invoice->number ?? '—' }}</td>
                                <td class="px-4 py-2">{{ $invoice->stableTenantName }}</td>
                                <td class="px-4 py-2">
                                    {{ $invoice->horseName ?? '—' }}
                                    @if ($invoice->billingPeriod)
                                        <div class="text-xs text-gray-500">{{ $invoice->billingPeriod }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-2">{{ $invoice->issuedAt?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-4 py-2">{{ $invoice->dueAt?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-4 py-2">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                        'bg-amber-100 text-amber-800' => $invoice->status === 'issued',
                                        'bg-emerald-100 text-emerald-800' => $invoice->status === 'paid',
                                        'bg-rose-100 text-rose-800' => $invoice->status === 'overdue',
                                        'bg-gray-100 text-gray-700' => ! in_array($invoice->status, ['issued', 'paid', 'overdue']),
                                    ])>
                                        {{ __('enums.invoice_status.'.$invoice->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right font-medium">
                                    {{ $this->formatCents($invoice->totalCents, $invoice->currency) }}
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <a
                                        href="{{ $this->showUrl($invoice) }}"
                                        class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                                    >
                                        {{ __('owner/invoices.table.view') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
