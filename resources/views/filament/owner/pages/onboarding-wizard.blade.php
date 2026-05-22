<x-filament-panels::page>
    <div class="max-w-3xl mx-auto">
        <div class="mb-6 rounded-2xl bg-gradient-to-r from-primary-50 to-amber-50 p-6 border border-primary-100">
            <h2 class="text-xl font-bold text-primary-900 mb-2">
                {{ __('owner/onboarding.welcome.heading') }}
            </h2>
            <p class="text-sm text-primary-800">
                {{ __('owner/onboarding.welcome.body') }}
            </p>
        </div>

        {{ $this->form }}
    </div>
</x-filament-panels::page>
