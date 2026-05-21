<x-filament-panels::page>
    @php($inv = $this->invoice)

    {{-- Hero z metadanymi --}}
    <div class="rounded-lg border border-primary-200 bg-primary-50 p-4 dark:border-primary-800 dark:bg-primary-900/20">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="text-xs uppercase tracking-wide text-primary-700 dark:text-primary-300">
                    {{ $inv->stableTenantName }}
                </div>
                <div class="text-2xl font-bold">
                    {{ $inv->number ?? __('owner/invoices.show.title_draft') }}
                </div>
                @if ($inv->billingPeriod)
                    <div class="mt-1 text-sm text-gray-500">
                        {{ __('owner/invoices.field.period') }}: {{ $inv->billingPeriod }}
                        @if ($inv->horseName)
                            · {{ $inv->horseName }}
                        @endif
                    </div>
                @endif
            </div>
            <div class="text-right">
                <div class="text-xs uppercase text-gray-500">{{ __('owner/invoices.field.total') }}</div>
                <div class="text-2xl font-bold text-primary-700 dark:text-primary-300">
                    {{ $this->formatCents($inv->totalCents) }}
                </div>
                @if ($inv->dueAt)
                    <div class="mt-1 text-xs text-gray-500">
                        {{ __('owner/invoices.field.due_at') }}: {{ $inv->dueAt->format('Y-m-d') }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Akcje: PDF + Pay (placeholdery na razie, w przyszłej iteracji się włączą) --}}
    <div class="flex flex-wrap gap-2">
        <x-filament::button color="gray" disabled icon="heroicon-o-arrow-down-tray">
            {{ __('owner/invoices.action.download_pdf_unavailable') }}
        </x-filament::button>
        <x-filament::button color="gray" disabled icon="heroicon-o-credit-card">
            {{ __('owner/invoices.action.pay_online_unavailable') }}
        </x-filament::button>
    </div>

    {{-- Sprzedawca + Nabywca --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <section class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900/40">
            <h2 class="mb-3 text-base font-semibold">{{ __('owner/invoices.section.seller') }}</h2>
            <div class="space-y-1 text-sm">
                <div class="font-medium">{{ $inv->sellerName }}</div>
                @if ($inv->sellerNip)
                    <div class="text-gray-500">{{ __('owner/invoices.field.nip') }}: {{ $inv->sellerNip }}</div>
                @endif
                @if ($inv->sellerAddress)
                    <div class="text-gray-500">{{ $inv->sellerAddress }}</div>
                @endif
                @if ($inv->sellerPostalCode || $inv->sellerCity)
                    <div class="text-gray-500">{{ trim(($inv->sellerPostalCode ?? '').' '.($inv->sellerCity ?? '')) }}</div>
                @endif
            </div>
        </section>
        <section class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900/40">
            <h2 class="mb-3 text-base font-semibold">{{ __('owner/invoices.section.buyer') }}</h2>
            <div class="space-y-1 text-sm">
                <div class="font-medium">{{ $inv->buyerName }}</div>
                @if ($inv->buyerNip)
                    <div class="text-gray-500">{{ __('owner/invoices.field.nip') }}: {{ $inv->buyerNip }}</div>
                @endif
                @if ($inv->buyerAddress)
                    <div class="text-gray-500">{{ $inv->buyerAddress }}</div>
                @endif
                @if ($inv->buyerPostalCode || $inv->buyerCity)
                    <div class="text-gray-500">{{ trim(($inv->buyerPostalCode ?? '').' '.($inv->buyerCity ?? '')) }}</div>
                @endif
            </div>
        </section>
    </div>

    {{-- Pozycje --}}
    <section class="space-y-3">
        <h2 class="text-base font-semibold">{{ __('owner/invoices.section.items') }}</h2>
        <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-900">
                    <tr>
                        <th class="px-4 py-2">{{ __('owner/invoices.item.position') }}</th>
                        <th class="px-4 py-2">{{ __('owner/invoices.item.name') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('owner/invoices.item.quantity') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('owner/invoices.item.unit_price') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('owner/invoices.item.vat_rate') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('owner/invoices.item.net') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('owner/invoices.item.total') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($inv->items as $item)
                        <tr>
                            <td class="px-4 py-2">{{ $item->position }}</td>
                            <td class="px-4 py-2">
                                <div class="font-medium">{{ $item->name }}</div>
                                @if ($item->description)
                                    <div class="text-xs text-gray-500">{{ $item->description }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ number_format($item->quantity, 2, ',', ' ') }} {{ $item->unit }}
                            </td>
                            <td class="px-4 py-2 text-right">{{ $this->formatCents($item->unitPriceCents) }}</td>
                            <td class="px-4 py-2 text-right">{{ is_numeric($item->vatRate) ? $item->vatRate.'%' : $item->vatRate }}</td>
                            <td class="px-4 py-2 text-right">{{ $this->formatCents($item->netCents) }}</td>
                            <td class="px-4 py-2 text-right font-medium">{{ $this->formatCents($item->totalCents) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <td colspan="5" class="px-4 py-2 text-right text-xs uppercase text-gray-500">
                            {{ __('owner/invoices.field.subtotal') }}
                        </td>
                        <td colspan="2" class="px-4 py-2 text-right">{{ $this->formatCents($inv->subtotalCents) }}</td>
                    </tr>
                    <tr>
                        <td colspan="5" class="px-4 py-2 text-right text-xs uppercase text-gray-500">
                            {{ __('owner/invoices.field.vat') }}
                        </td>
                        <td colspan="2" class="px-4 py-2 text-right">{{ $this->formatCents($inv->vatCents) }}</td>
                    </tr>
                    <tr class="bg-primary-50 dark:bg-primary-900/30">
                        <td colspan="5" class="px-4 py-3 text-right text-sm font-bold uppercase">
                            {{ __('owner/invoices.field.total') }}
                        </td>
                        <td colspan="2" class="px-4 py-3 text-right text-lg font-bold text-primary-700 dark:text-primary-300">
                            {{ $this->formatCents($inv->totalCents) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    @if ($inv->notes)
        <section class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900/40">
            <h2 class="mb-2 text-base font-semibold">{{ __('owner/invoices.section.notes') }}</h2>
            <div class="text-sm text-gray-700 dark:text-gray-300">{{ $inv->notes }}</div>
        </section>
    @endif
</x-filament-panels::page>
