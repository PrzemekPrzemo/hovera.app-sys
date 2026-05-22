<x-filament-widgets::widget>
    @php $invoices = $this->getRecent(); @endphp
    @if (count($invoices) > 0)
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <div>
                    <div class="text-sm font-bold text-gray-900 dark:text-gray-100">
                        {{ __('owner/recent_invoices.heading') }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        {{ __('owner/recent_invoices.subheading') }}
                    </div>
                </div>
                <a href="/owner/invoices" class="text-xs font-semibold text-primary-600 hover:underline whitespace-nowrap">
                    {{ __('owner/recent_invoices.see_all') }} →
                </a>
            </div>
            <div class="space-y-2">
                @foreach ($invoices as $i)
                    <a href="{{ $i['url'] }}"
                       class="block rounded-lg border border-gray-200 dark:border-gray-700 px-4 py-3 hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/30 transition">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                                    @if ($i['number'])
                                        {{ $i['number'] }}
                                    @else
                                        <span class="text-gray-500">{{ __('owner/recent_invoices.draft') }}</span>
                                    @endif
                                    <span class="ml-2 text-xs font-normal text-gray-500 dark:text-gray-400">· {{ $i['stable_name'] }}</span>
                                </div>
                                @if ($i['issued_at'])
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        {{ __('owner/recent_invoices.issued', ['date' => $i['issued_at']]) }}
                                    </div>
                                @endif
                            </div>
                            <div class="text-right shrink-0">
                                <div class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                    {{ $i['total'] }} {{ $i['currency'] }}
                                </div>
                                @if ($i['paid'])
                                    <div class="text-xs text-green-600 dark:text-green-400 font-semibold mt-0.5">
                                        ✓ {{ __('owner/recent_invoices.paid') }}
                                    </div>
                                @else
                                    <div class="text-xs text-amber-600 dark:text-amber-400 font-semibold mt-0.5">
                                        {{ __('owner/recent_invoices.unpaid') }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</x-filament-widgets::widget>
