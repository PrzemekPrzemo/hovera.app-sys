<x-filament-panels::page>
    <form wire:submit="submit" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit" icon="heroicon-o-paper-airplane">
                {{ __('owner/transport.order.action.submit') }}
            </x-filament::button>
        </div>
    </form>

    <div class="mt-6 rounded-lg bg-primary-50 p-4 text-sm text-primary-800 dark:bg-primary-900/30 dark:text-primary-200">
        {{ __('owner/transport.order.info.how_it_works') }}
    </div>
</x-filament-panels::page>
