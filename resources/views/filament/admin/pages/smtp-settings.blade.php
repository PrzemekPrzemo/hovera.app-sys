<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex justify-between gap-3">
            <x-filament::button type="button" color="gray" icon="heroicon-o-paper-airplane" wire:click="sendTestEmail">
                {{ __('admin/smtp.action.send_test_button') }}
            </x-filament::button>

            <x-filament::button type="submit" icon="heroicon-o-check">
                {{ __('admin/smtp.action.save_button') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
