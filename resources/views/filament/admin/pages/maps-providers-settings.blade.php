<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex justify-end">
            <x-filament::button type="submit" icon="heroicon-o-check">
                {{ __('admin/maps_providers.action.save_button') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
