<x-filament-widgets::widget>
    @if (! $dismissed)
        <div class="rounded-xl border border-amber-300 dark:border-amber-700 bg-gradient-to-r from-amber-50 to-white dark:from-amber-900/30 dark:to-gray-900 px-5 py-4 shadow-sm flex items-center justify-between gap-4">
            <div class="flex items-center gap-4 flex-1 min-w-0">
                <div class="shrink-0 rounded-lg bg-amber-100 dark:bg-amber-900/50 p-2">
                    <x-heroicon-o-truck class="h-6 w-6 text-amber-600 dark:text-amber-300" />
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-sm text-gray-900 dark:text-gray-100">
                        {{ __('app/transport_entry.promo_widget.title') }}
                    </div>
                    <div class="text-xs text-gray-600 dark:text-gray-300 mt-0.5">
                        {{ __('app/transport_entry.promo_widget.subtitle') }}
                        <span class="ml-1 text-gray-500 dark:text-gray-400">·</span>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('app/transport_entry.promo_widget.stats', ['count' => $this->getVerifiedTransportersCount()]) }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ $this->getEntryUrl() }}"
                   class="rounded-md bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 text-xs font-bold whitespace-nowrap">
                    {{ __('app/transport_entry.promo_widget.cta') }} →
                </a>
                <button wire:click="dismiss"
                        title="{{ __('app/transport_entry.promo_widget.dismiss') }}"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1">
                    <x-heroicon-o-x-mark class="h-4 w-4" />
                </button>
            </div>
        </div>
    @endif
</x-filament-widgets::widget>
