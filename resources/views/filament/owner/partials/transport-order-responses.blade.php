@php $responses = $getState()['responses'] ?? []; @endphp
<div class="space-y-2">
    @foreach ($responses as $r)
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-3 flex items-center justify-between gap-3">
            <div class="flex-1 min-w-0">
                <div class="font-semibold text-sm text-gray-900 dark:text-gray-100">
                    {{ $r['transporter_name'] }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    @if ($r['date'])
                        {{ __('owner/transport.orders.response.proposed_date', ['date' => $r['date']]) }}
                    @endif
                </div>
            </div>
            <div class="text-right shrink-0">
                <div class="font-bold text-sm text-gray-900 dark:text-gray-100">
                    {{ $r['price'] }} {{ $r['currency'] }}
                </div>
                @if ($r['link'])
                    <a href="{{ $r['link'] }}" target="_blank" rel="noopener"
                       class="inline-block mt-1 text-xs font-semibold text-primary-600 hover:underline">
                        {{ __('owner/transport.orders.response.open') }} →
                    </a>
                @endif
            </div>
        </div>
    @endforeach
</div>
