<x-filament-widgets::widget>
    @php $invite = $this->getInvite(); @endphp
    @if (! $dismissed && $invite !== null)
        <div class="rounded-xl border border-primary-300 dark:border-primary-700 bg-gradient-to-r from-primary-50 to-amber-50 dark:from-primary-900/30 dark:to-amber-900/20 px-5 py-4 flex items-start gap-4 shadow-sm">
            <div class="shrink-0 rounded-lg bg-primary-100 dark:bg-primary-900/50 p-2 mt-0.5">
                <x-heroicon-o-building-storefront class="h-6 w-6 text-primary-600 dark:text-primary-300" />
            </div>
            <div class="flex-1 min-w-0">
                <div class="font-bold text-sm text-gray-900 dark:text-gray-100">
                    {{ __('owner/invite_origin.title', ['stable' => $invite['stable_name']]) }}
                </div>
                <div class="text-xs text-gray-600 dark:text-gray-300 mt-0.5">
                    {{ __('owner/invite_origin.body') }}
                    @if ($this->getReceivedAtRelative() !== '')
                        <span class="ml-1 text-gray-500 dark:text-gray-400">·</span>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('owner/invite_origin.received', ['ago' => $this->getReceivedAtRelative()]) }}</span>
                    @endif
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <a href="/owner/horses/create"
                       class="rounded-md bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 text-xs font-bold whitespace-nowrap">
                        {{ __('owner/invite_origin.cta_add_horse') }} →
                    </a>
                    @if ($invite['stable_slug'])
                        <a href="/s/{{ $invite['stable_slug'] }}" target="_blank" rel="noopener"
                           class="rounded-md border border-primary-200 dark:border-primary-700 text-primary-700 dark:text-primary-300 px-3 py-1.5 text-xs font-semibold whitespace-nowrap hover:bg-primary-50 dark:hover:bg-primary-900/50">
                            {{ __('owner/invite_origin.cta_view_stable') }}
                        </a>
                    @endif
                </div>
            </div>
            <button wire:click="dismiss"
                    title="{{ __('owner/invite_origin.dismiss') }}"
                    class="shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1">
                <x-heroicon-o-x-mark class="h-4 w-4" />
            </button>
        </div>
    @endif
</x-filament-widgets::widget>
