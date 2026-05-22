<x-filament-panels::page>
    @php
        $colors = [
            'ok' => ['bg' => 'bg-green-50 dark:bg-green-900/30', 'text' => 'text-green-800 dark:text-green-200', 'badge' => 'bg-green-500'],
            'soon' => ['bg' => 'bg-amber-50 dark:bg-amber-900/30', 'text' => 'text-amber-800 dark:text-amber-200', 'badge' => 'bg-amber-500'],
            'expired' => ['bg' => 'bg-red-50 dark:bg-red-900/30', 'text' => 'text-red-800 dark:text-red-200', 'badge' => 'bg-red-600'],
        ];
        $docs = $this->getDocuments();
    @endphp

    @if ($driver === null)
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-5 py-4">
            <div class="text-sm text-gray-700 dark:text-gray-300">
                {{ __('transport/my_documents.no_driver_record') }}
            </div>
        </div>
    @else
        <div class="mb-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4">
            <div class="text-sm font-bold text-gray-900 dark:text-gray-100">
                {{ $driver->full_name }}
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                @if ($driver->phone)
                    {{ $driver->phone }}
                @endif
                @if ($driver->email)
                    @if ($driver->phone) · @endif {{ $driver->email }}
                @endif
            </div>
        </div>

        <div class="space-y-3">
            @forelse ($docs as $doc)
                @php $c = $colors[$doc['status']] ?? $colors['ok']; @endphp
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 {{ $c['bg'] }} px-5 py-4 flex items-start gap-4">
                    <div class="shrink-0 mt-1">
                        <span class="inline-block w-3 h-3 rounded-full {{ $c['badge'] }}"></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-bold {{ $c['text'] }}">
                            {{ $doc['label'] }}
                            <span class="font-normal text-xs ml-2 opacity-70">
                                {{ __('transport/my_documents.status.'.$doc['status']) }}
                            </span>
                        </div>
                        <div class="text-sm {{ $c['text'] }} opacity-80 mt-0.5">
                            @if ($doc['value'])
                                {{ $doc['value'] }}
                            @endif
                            @if ($doc['expires_at'])
                                · {{ __('transport/my_documents.expires_at', ['date' => $doc['expires_at']]) }}
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-5 py-4 text-sm text-gray-600 dark:text-gray-400">
                    {{ __('transport/my_documents.empty') }}
                </div>
            @endforelse
        </div>

        <div class="mt-6 text-xs text-gray-500 dark:text-gray-400">
            {{ __('transport/my_documents.hint') }}
        </div>
    @endif
</x-filament-panels::page>
