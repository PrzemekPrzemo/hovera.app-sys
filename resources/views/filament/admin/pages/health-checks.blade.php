<x-filament-panels::page>
    @php
        $colors = [
            'ok' => ['bg' => 'bg-green-50 dark:bg-green-900/30', 'text' => 'text-green-800 dark:text-green-200', 'badge' => 'bg-green-500'],
            'degraded' => ['bg' => 'bg-amber-50 dark:bg-amber-900/30', 'text' => 'text-amber-800 dark:text-amber-200', 'badge' => 'bg-amber-500'],
            'error' => ['bg' => 'bg-red-50 dark:bg-red-900/30', 'text' => 'text-red-800 dark:text-red-200', 'badge' => 'bg-red-600'],
            'not_configured' => ['bg' => 'bg-gray-50 dark:bg-gray-800', 'text' => 'text-gray-700 dark:text-gray-300', 'badge' => 'bg-gray-400'],
            'unknown' => ['bg' => 'bg-blue-50 dark:bg-blue-900/30', 'text' => 'text-blue-800 dark:text-blue-200', 'badge' => 'bg-blue-500'],
        ];
    @endphp

    <div class="space-y-3">
        @foreach ($rows as $row)
            @php $c = $colors[$row['status']] ?? $colors['unknown']; @endphp
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 {{ $c['bg'] }} px-5 py-4 flex items-start gap-4">
                <div class="shrink-0 mt-1">
                    <span class="inline-block w-3 h-3 rounded-full {{ $c['badge'] }}"></span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-bold {{ $c['text'] }}">
                        {{ $row['label'] }}
                    </div>
                    <div class="text-sm {{ $c['text'] }} opacity-80 mt-0.5">
                        {{ __('admin/health_checks.status.'.$row['status']) }}@if (! empty($row['detail'])) · {{ $row['detail'] }}@endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-6 text-xs text-gray-500 dark:text-gray-400">
        {{ __('admin/health_checks.hint') }}
    </div>
</x-filament-panels::page>
