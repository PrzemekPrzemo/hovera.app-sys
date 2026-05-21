<x-filament-panels::page>
    <div class="space-y-4">
        <div class="text-sm text-gray-600 dark:text-gray-400">
            @if ($this->activeHorseName())
                {{ __('owner/invoices.list.description_filtered', ['horse' => $this->activeHorseName()]) }}
            @else
                {{ __('owner/invoices.list.description') }}
            @endif
        </div>

        {{-- C.7 — Yearly totals banner + year filter chips + CSV export --}}
        @if (! empty($this->yearlyTotals))
            <div class="rounded-lg border border-primary-200 bg-primary-50 p-3 dark:border-primary-800 dark:bg-primary-900/20">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-baseline gap-3">
                        <span class="text-xs uppercase tracking-wide text-primary-700 dark:text-primary-300">
                            @if ($this->yearFilter)
                                {{ __('owner/invoices.list.total_year', ['year' => $this->yearFilter]) }}
                            @else
                                {{ __('owner/invoices.list.total_all') }}
                            @endif
                        </span>
                        <span class="text-xl font-bold text-primary-700 dark:text-primary-300">
                            {{ $this->formatCents($this->currentYearTotal()) }}
                        </span>
                    </div>
                    <a
                        href="{{ $this->csvExportUrl() }}"
                        class="inline-flex items-center gap-1 rounded-md border border-primary-300 bg-white px-3 py-1.5 text-xs font-medium text-primary-700 hover:bg-primary-100 dark:border-primary-700 dark:bg-gray-900 dark:text-primary-300 dark:hover:bg-primary-900/40"
                    >
                        <x-filament::icon icon="heroicon-o-arrow-down-tray" class="h-4 w-4" />
                        {{ __('owner/invoices.list.export_csv') }}
                    </a>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <a
                        href="{{ $this->yearFilterUrl(null) }}"
                        @class([
                            'inline-flex items-center rounded-full px-3 py-1 text-xs font-medium transition',
                            'bg-primary-600 text-white' => $this->yearFilter === null,
                            'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' => $this->yearFilter !== null,
                        ])
                    >
                        {{ __('owner/invoices.list.year_all') }}
                    </a>
                    @foreach ($this->yearlyTotals as $yr => $totalCents)
                        <a
                            href="{{ $this->yearFilterUrl($yr) }}"
                            @class([
                                'inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium transition',
                                'bg-primary-600 text-white' => $this->yearFilter === $yr,
                                'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' => $this->yearFilter !== $yr,
                            ])
                        >
                            <span>{{ $yr }}</span>
                            <span class="opacity-70">·</span>
                            <span class="font-mono">{{ $this->formatCents($totalCents) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

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
