<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Hero ----------------------------------------------------------- --}}
        <div class="rounded-2xl border border-amber-300 bg-gradient-to-br from-amber-50 to-white dark:from-amber-900/30 dark:to-gray-900 px-6 py-6 shadow-sm">
            <div class="flex items-start gap-4">
                <div class="shrink-0 rounded-xl bg-amber-100 dark:bg-amber-900/50 p-3">
                    <x-heroicon-o-truck class="h-8 w-8 text-amber-600 dark:text-amber-300" />
                </div>
                <div class="flex-1">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">
                        {{ __('app/transport_entry.hero.title') }}
                    </h2>
                    <p class="mt-1 text-sm text-emerald-700 dark:text-emerald-400 font-semibold">
                        {{ __('app/transport_entry.hero.free_badge') }}
                    </p>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                        {{ __('app/transport_entry.hero.subtitle', ['count' => $this->getVerifiedTransportersCount()]) }}
                    </p>
                </div>
            </div>
        </div>

        {{-- 3 CTA cards --------------------------------------------------- --}}
        <div class="grid gap-4 md:grid-cols-3">

            {{-- Broadcast --}}
            <a href="{{ $this->getBroadcastUrl() }}"
               class="group rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 shadow-sm hover:shadow-md hover:border-primary-400 transition">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-megaphone class="h-7 w-7 text-primary-600 dark:text-primary-400" />
                    <h3 class="font-bold text-gray-900 dark:text-gray-100 group-hover:text-primary-700 dark:group-hover:text-primary-300">
                        {{ __('app/transport_entry.cta.broadcast.title') }}
                    </h3>
                </div>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                    {{ __('app/transport_entry.cta.broadcast.subtitle') }}
                </p>
                <p class="mt-4 text-sm font-semibold text-primary-600 dark:text-primary-400">
                    {{ __('app/transport_entry.cta.broadcast.action') }} →
                </p>
            </a>

            {{-- Directory --}}
            <a href="{{ $this->getDirectoryUrl() }}"
               class="group rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 shadow-sm hover:shadow-md hover:border-primary-400 transition">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-building-storefront class="h-7 w-7 text-primary-600 dark:text-primary-400" />
                    <h3 class="font-bold text-gray-900 dark:text-gray-100 group-hover:text-primary-700 dark:group-hover:text-primary-300">
                        {{ __('app/transport_entry.cta.directory.title') }}
                    </h3>
                </div>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                    {{ __('app/transport_entry.cta.directory.subtitle') }}
                </p>
                <p class="mt-4 text-sm font-semibold text-primary-600 dark:text-primary-400">
                    {{ __('app/transport_entry.cta.directory.action') }} →
                </p>
            </a>

            {{-- Favorites --}}
            <a href="{{ $this->getFavoritesUrl() }}"
               class="group rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 shadow-sm hover:shadow-md hover:border-primary-400 transition">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-star class="h-7 w-7 text-primary-600 dark:text-primary-400" />
                    <h3 class="font-bold text-gray-900 dark:text-gray-100 group-hover:text-primary-700 dark:group-hover:text-primary-300">
                        {{ __('app/transport_entry.cta.favorites.title') }}
                    </h3>
                </div>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                    {{ __('app/transport_entry.cta.favorites.subtitle', ['count' => $this->getFavoritesCount()]) }}
                </p>
                <p class="mt-4 text-sm font-semibold text-primary-600 dark:text-primary-400">
                    {{ __('app/transport_entry.cta.favorites.action') }} →
                </p>
            </a>

        </div>

        {{-- Stats strip --------------------------------------------------- --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-5 py-4 shadow-sm">
            <div class="flex items-center gap-3">
                <x-heroicon-o-clipboard-document-list class="h-5 w-5 text-gray-500 dark:text-gray-400" />
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 font-semibold">
                        {{ __('app/transport_entry.stats.your_leads') }}
                    </div>
                    <div class="text-lg font-bold text-gray-900 dark:text-gray-100">
                        {{ $this->getStableLeadsCount() }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Disclaimer ---------------------------------------------------- --}}
        <p class="text-xs text-gray-500 dark:text-gray-400 italic leading-relaxed">
            {!! __('app/transport_entry.disclaimer') !!}
        </p>

    </div>
</x-filament-panels::page>
