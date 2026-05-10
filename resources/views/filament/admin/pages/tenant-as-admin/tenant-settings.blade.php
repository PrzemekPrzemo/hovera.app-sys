<x-filament-panels::page>
    <div class="rounded-md bg-amber-50 dark:bg-amber-900/30 p-3 text-sm text-amber-800 dark:text-amber-200 mb-4">
        {{ __('admin/back-office.settings.warning_no_impersonation') }}
    </div>

    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex justify-end gap-2">
            <x-filament::button color="gray" tag="a" :href="url('/admin/tenants/'.$tenantId.'/edit')">
                {{ __('admin/back-office.common.back') }}
            </x-filament::button>
            <x-filament::button type="submit">{{ __('admin/back-office.common.save') }}</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
