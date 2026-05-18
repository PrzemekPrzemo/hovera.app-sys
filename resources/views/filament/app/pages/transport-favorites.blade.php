<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-lg border border-amber-300 bg-amber-50 dark:bg-amber-900/30 px-4 py-3 text-sm">
            <strong>{{ __('app/transport_favorites.intro.title') }}:</strong>
            {{ __('app/transport_favorites.intro.body', ['limit' => $limit, 'current' => $currentCount]) }}
        </div>

        <div>
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('app/transport_favorites.search_placeholder') }}"
                class="w-full max-w-md rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm">
        </div>

        @if ($rows->isEmpty())
            <p class="text-sm text-gray-500 italic">{{ __('app/transport_favorites.empty') }}</p>
        @else
            <div class="grid gap-2">
                @foreach ($rows as $tenant)
                    @php $isFav = in_array($tenant->id, $favoriteIds, true); @endphp
                    <div class="flex items-center justify-between gap-3 rounded-lg border {{ $isFav ? 'border-amber-300 bg-amber-50 dark:bg-amber-900/20' : 'border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900' }} px-4 py-3">
                        <div class="flex-1 min-w-0">
                            <div class="font-bold text-sm">
                                {{ $tenant->legal_name ?: $tenant->name }}
                                @if ($isFav)
                                    <span class="ml-1 text-amber-500">★</span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-500">
                                @if ($tenant->tax_id) NIP {{ $tenant->tax_id }} · @endif
                                {{ $tenant->slug }}
                            </div>
                        </div>
                        <button
                            wire:click="toggle('{{ $tenant->id }}')"
                            class="rounded-md px-3 py-1.5 text-xs font-bold whitespace-nowrap {{ $isFav ? 'bg-rose-100 text-rose-700 hover:bg-rose-200 dark:bg-rose-900/40 dark:text-rose-300' : 'bg-primary-600 text-white hover:bg-primary-700' }}">
                            @if ($isFav)
                                ✕ {{ __('app/transport_favorites.action.remove') }}
                            @else
                                ★ {{ __('app/transport_favorites.action.add') }}
                            @endif
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
