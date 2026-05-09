<x-filament-panels::page>
    <p>{{ __('billing.page.redirecting') }}</p>
    <p>
        <a href="{{ route('billing.show') }}" class="text-primary-600 underline">
            {{ __('billing.page.click_here') }}
        </a>
    </p>
</x-filament-panels::page>
