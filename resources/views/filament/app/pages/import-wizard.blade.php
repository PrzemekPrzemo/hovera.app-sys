<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-sm text-gray-700 dark:text-gray-300">
                {{ __('import-wizard.intro') }}
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ url('/app/import-wizard/template/clients') }}"
                   class="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                    <x-filament::icon icon="heroicon-o-arrow-down-tray" class="h-4 w-4" />
                    {{ __('import-wizard.template.clients') }}
                </a>
                <a href="{{ url('/app/import-wizard/template/horses') }}"
                   class="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                    <x-filament::icon icon="heroicon-o-arrow-down-tray" class="h-4 w-4" />
                    {{ __('import-wizard.template.horses') }}
                </a>
            </div>
        </div>

        <form wire:submit="runImport">
            {{ $this->form }}
        </form>

        @if ($this->result)
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                    {{ __('import-wizard.result.heading') }}
                </h3>
                <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                    {{ __('import-wizard.result.summary', ['ok' => $this->result['imported'], 'failed' => $this->result['failed']]) }}
                </p>

                @if (! empty($this->result['errors']))
                    <div class="mt-3 max-h-64 overflow-auto rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm dark:border-amber-700/40 dark:bg-amber-950/30">
                        <ul class="list-inside list-disc space-y-1">
                            @foreach ($this->result['errors'] as $err)
                                <li>
                                    <span class="font-mono text-xs text-amber-900 dark:text-amber-200">#{{ $err['row'] }}</span>
                                    {{ $err['message'] }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
