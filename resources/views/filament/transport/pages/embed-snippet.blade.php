<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <x-filament::section>
            <x-slot name="heading">{{ __('transport/embed_snippet.section.snippet') }}</x-slot>
            <x-slot name="description">{{ __('transport/embed_snippet.section.snippet_description') }}</x-slot>

            <div class="space-y-3">
                <textarea
                    id="hovera-embed-snippet-code"
                    readonly
                    rows="14"
                    class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 font-mono text-xs leading-relaxed text-gray-700 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    onclick="this.select()"
                >{{ $this->getSnippetCode() }}</textarea>

                <div class="flex gap-2">
                    <x-filament::button
                        type="button"
                        size="sm"
                        color="gray"
                        icon="heroicon-o-clipboard"
                        x-on:click="
                            const ta = document.getElementById('hovera-embed-snippet-code');
                            ta.select();
                            navigator.clipboard.writeText(ta.value);
                            $tooltip('{{ __('transport/embed_snippet.action.copied') }}', { timeout: 1500 });
                        "
                    >
                        {{ __('transport/embed_snippet.action.copy') }}
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    </form>
</x-filament-panels::page>
