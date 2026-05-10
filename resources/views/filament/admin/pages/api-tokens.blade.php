<x-filament-panels::page>
    {{ $this->table }}

    <x-filament::modal id="token-revealed" :close-by-clicking-away="false" width="lg">
        <x-slot name="heading">
            {{ __('admin/api-management.tokens.modal.heading') }}
        </x-slot>

        <x-slot name="description">
            <div class="text-warning-600 dark:text-warning-400 font-semibold">
                {{ __('admin/api-management.tokens.modal.warning') }}
            </div>
        </x-slot>

        @if ($generatedToken)
            <div class="space-y-3">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('admin/api-management.tokens.modal.name_label') }}:
                    <span class="font-mono font-semibold">{{ $generatedTokenName }}</span>
                </div>

                <div class="rounded-lg border border-warning-300 bg-warning-50 p-3 dark:border-warning-700 dark:bg-warning-900/20">
                    <code
                        class="block break-all font-mono text-xs text-gray-900 dark:text-gray-100"
                        x-data="{}"
                        x-ref="token"
                    >{{ $generatedToken }}</code>
                </div>

                <div class="flex justify-end gap-2">
                    <x-filament::button
                        x-data="{}"
                        x-on:click="navigator.clipboard.writeText(@js($generatedToken))"
                        icon="heroicon-o-clipboard-document"
                    >
                        {{ __('admin/api-management.tokens.modal.copy') }}
                    </x-filament::button>
                </div>
            </div>
        @endif
    </x-filament::modal>
</x-filament-panels::page>
