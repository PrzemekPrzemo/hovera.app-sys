@if (empty($rows))
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('import-wizard.preview.empty') }}</p>
@else
    @php
        $fields = collect($mapping)
            ->filter(fn ($h) => filled($h))
            ->keys()
            ->values()
            ->all();
    @endphp
    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-300">#</th>
                    @foreach ($fields as $f)
                        <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-300">{{ $f }}</th>
                    @endforeach
                    <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-300">
                        {{ __('import-wizard.preview.status') }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                @foreach ($rows as $i => $row)
                    <tr class="{{ $row['ok'] ? '' : 'bg-amber-50 dark:bg-amber-950/30' }}">
                        <td class="px-3 py-2 font-mono text-xs text-gray-500">{{ $i + 1 }}</td>
                        @foreach ($fields as $f)
                            <td class="px-3 py-2 text-gray-900 dark:text-gray-100">
                                {{ data_get($row['data'], $f) }}
                            </td>
                        @endforeach
                        <td class="px-3 py-2">
                            @if ($row['ok'])
                                <span class="inline-flex items-center gap-1 rounded-md bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/40 dark:text-green-200">
                                    {{ __('import-wizard.preview.ok') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-md bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                                    {{ implode(' · ', $row['errors']) }}
                                </span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('import-wizard.preview.note') }}</p>
@endif
