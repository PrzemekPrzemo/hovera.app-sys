<x-filament-widgets::widget>
    <div class="rounded-xl border border-primary-300 dark:border-primary-700 bg-gradient-to-r from-primary-50 to-amber-50 dark:from-primary-900/30 dark:to-amber-900/20 px-5 py-4 shadow-sm flex items-center justify-between gap-4">
        <div class="flex items-center gap-4 flex-1 min-w-0">
            <div class="shrink-0 rounded-lg bg-primary-100 dark:bg-primary-900/50 p-2">
                <x-heroicon-o-sparkles class="h-6 w-6 text-primary-600 dark:text-primary-300" />
            </div>
            <div class="flex-1 min-w-0">
                <div class="font-bold text-sm text-gray-900 dark:text-gray-100">
                    {{ __('owner/onboarding.banner.title') }}
                </div>
                <div class="text-xs text-gray-600 dark:text-gray-300 mt-0.5">
                    {{ __('owner/onboarding.banner.subtitle') }}
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ $this->getWizardUrl() }}"
               class="rounded-md bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 text-xs font-bold whitespace-nowrap">
                {{ __('owner/onboarding.banner.cta') }} →
            </a>
        </div>
    </div>
</x-filament-widgets::widget>
