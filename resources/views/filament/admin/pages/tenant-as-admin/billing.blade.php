<x-filament-panels::page>
    @if ($loadError)
        <div class="rounded-md bg-red-50 dark:bg-red-900/30 p-3 text-sm text-red-800 dark:text-red-200 mb-4">
            {{ __('admin/back-office.billing.load_error') }}: {{ $loadError }}
        </div>
    @endif

    @if (count($invoices) === 0)
        <div class="rounded-md bg-gray-50 dark:bg-gray-800 p-6 text-center text-sm text-gray-600 dark:text-gray-300">
            {{ __('admin/back-office.billing.empty') }}
        </div>
    @else
        <div class="overflow-x-auto rounded-md border border-gray-200 dark:border-gray-700">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2 text-left">{{ __('admin/back-office.billing.col.number') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('admin/back-office.billing.col.created') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('admin/back-office.billing.col.period') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('admin/back-office.billing.col.amount') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('admin/back-office.billing.col.status') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('admin/back-office.billing.col.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($invoices as $invoice)
                        <tr>
                            <td class="px-3 py-2 font-mono text-xs">{{ $invoice['number'] ?: $invoice['id'] }}</td>
                            <td class="px-3 py-2">
                                {{ $invoice['created']?->translatedFormat('d.m.Y H:i') ?? '—' }}
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-500">
                                @if ($invoice['period_start'] && $invoice['period_end'])
                                    {{ $invoice['period_start']->translatedFormat('d.m.Y') }} – {{ $invoice['period_end']->translatedFormat('d.m.Y') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right font-medium">
                                {{ number_format(($invoice['amount_paid'] ?: $invoice['amount_due']) / 100, 2, ',', ' ') }}
                                <span class="text-xs text-gray-500">{{ $invoice['currency'] }}</span>
                            </td>
                            <td class="px-3 py-2">
                                @php
                                    $colors = [
                                        'paid' => 'bg-green-100 text-green-800',
                                        'open' => 'bg-amber-100 text-amber-800',
                                        'uncollectible' => 'bg-red-100 text-red-800',
                                        'void' => 'bg-gray-100 text-gray-800',
                                        'draft' => 'bg-gray-100 text-gray-800',
                                    ];
                                    $color = $colors[$invoice['status']] ?? 'bg-gray-100 text-gray-800';
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $color }}">
                                    {{ $invoice['status'] }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right space-x-2">
                                @if ($invoice['hosted_invoice_url'])
                                    <a href="{{ $invoice['hosted_invoice_url'] }}" target="_blank" rel="noopener" class="text-primary-600 hover:underline text-xs">
                                        {{ __('admin/back-office.billing.actions.view') }}
                                    </a>
                                @endif
                                @if ($invoice['invoice_pdf'])
                                    <a href="{{ $invoice['invoice_pdf'] }}" target="_blank" rel="noopener" class="text-primary-600 hover:underline text-xs">
                                        {{ __('admin/back-office.billing.actions.pdf') }}
                                    </a>
                                @endif
                                @if ($invoice['status'] === 'paid' && $invoice['charge'])
                                    <button type="button"
                                        wire:click="startRefund('{{ $invoice['id'] }}')"
                                        class="text-red-600 hover:underline text-xs">
                                        {{ __('admin/back-office.billing.actions.refund') }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($refundInvoiceId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-lg bg-white dark:bg-gray-900 p-6 shadow-xl">
                <h3 class="text-lg font-semibold mb-4">
                    {{ __('admin/back-office.billing.refund.modal_heading') }}
                </h3>
                <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">
                    {{ __('admin/back-office.billing.refund.modal_body', ['id' => $refundInvoiceId]) }}
                </p>

                <label class="flex items-center gap-2 mb-3 text-sm">
                    <input type="checkbox" wire:model.live="refundData.full" class="rounded">
                    {{ __('admin/back-office.billing.refund.full_label') }}
                </label>

                @if (! ($refundData['full'] ?? true))
                    <div class="mb-3">
                        <label class="block text-sm mb-1">{{ __('admin/back-office.billing.refund.amount_label') }}</label>
                        <input type="number" min="1" wire:model="refundData.amount_cents" class="w-full rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700">
                        <p class="text-xs text-gray-500 mt-1">{{ __('admin/back-office.billing.refund.amount_helper') }}</p>
                    </div>
                @endif

                <div class="mb-4">
                    <label class="block text-sm mb-1">{{ __('admin/back-office.billing.refund.reason_label') }}</label>
                    <textarea wire:model="refundData.reason" rows="3" minlength="5" maxlength="500"
                        class="w-full rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700"></textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <x-filament::button color="gray" wire:click="cancelRefund">
                        {{ __('admin/back-office.common.cancel') }}
                    </x-filament::button>
                    <x-filament::button color="danger" wire:click="confirmRefund">
                        {{ __('admin/back-office.billing.refund.confirm') }}
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
