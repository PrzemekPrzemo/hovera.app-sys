@php /** @var \App\Filament\App\Pages\Help $this */ @endphp

<x-filament-panels::page>
    {{-- Tabs: instrukcja per rola / dokumentacja prawna --}}
    <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 dark:border-gray-700 pb-3">
        <button
            type="button"
            wire:click="switchView('manual')"
            class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition
                   {{ $this->activeView === 'manual'
                        ? 'bg-primary-500 text-white shadow-sm'
                        : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800' }}">
            @svg('heroicon-o-book-open', 'h-4 w-4')
            {{ __('pages.help.tab.manual') }}
        </button>
        <button
            type="button"
            wire:click="switchView('legal')"
            class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition
                   {{ $this->activeView === 'legal'
                        ? 'bg-primary-500 text-white shadow-sm'
                        : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800' }}">
            @svg('heroicon-o-scale', 'h-4 w-4')
            {{ __('pages.help.tab.legal') }}
        </button>
    </div>

    @if ($this->activeView === 'manual')
        {{-- Persona switcher: 4 karty z ilustracją per rola --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 mt-4">
            @foreach ($this->personaCards() as $card)
                @php $active = $this->activePersona() === $card['key']; @endphp
                <button
                    type="button"
                    wire:click="switchPersona('{{ $card['key'] }}')"
                    class="group flex flex-col items-start gap-2 rounded-xl border p-3 text-left transition
                           {{ $active
                                ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20 ring-2 ring-primary-500/30'
                                : 'border-gray-200 bg-white hover:border-primary-300 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-700' }}">
                    <div class="flex w-full items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="rounded-lg bg-primary-100 p-1.5 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300">
                                @svg($card['icon'], 'h-5 w-5')
                            </span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                {{ $card['label'] }}
                            </span>
                        </div>
                        @if ($active)
                            @svg('heroicon-s-check-circle', 'h-5 w-5 text-primary-500')
                        @endif
                    </div>
                    <img src="{{ $card['illustration'] }}" alt="" class="h-24 w-full rounded-lg bg-gray-50 object-contain p-1 dark:bg-gray-800/60" loading="lazy" />
                    <p class="text-xs text-gray-600 dark:text-gray-400">{{ $card['description'] }}</p>
                </button>
            @endforeach
        </div>

        {{-- Treść instrukcji --}}
        <article class="prose prose-sm max-w-none dark:prose-invert mt-6
                    prose-headings:text-gray-900 dark:prose-headings:text-gray-100
                    prose-h1:text-2xl prose-h1:font-bold prose-h1:mb-4
                    prose-h2:text-xl prose-h2:font-semibold prose-h2:mt-6 prose-h2:mb-3
                    prose-h3:text-lg prose-h3:font-semibold prose-h3:mt-4 prose-h3:mb-2
                    prose-p:leading-relaxed prose-p:my-3
                    prose-li:my-1
                    prose-table:my-4
                    prose-th:bg-gray-50 dark:prose-th:bg-gray-800 prose-th:px-3 prose-th:py-2
                    prose-td:px-3 prose-td:py-2 prose-td:border-t prose-td:border-gray-200 dark:prose-td:border-gray-700
                    prose-code:bg-gray-100 dark:prose-code:bg-gray-800 prose-code:px-1 prose-code:py-0.5 prose-code:rounded prose-code:text-sm
                    prose-pre:bg-gray-900 prose-pre:text-gray-100 prose-pre:p-4 prose-pre:rounded-lg
                    prose-strong:font-semibold
                    prose-blockquote:border-l-4 prose-blockquote:border-primary-500 prose-blockquote:bg-primary-50 dark:prose-blockquote:bg-primary-900/20 prose-blockquote:py-2 prose-blockquote:px-4 prose-blockquote:rounded-r
                    prose-a:text-primary-600 dark:prose-a:text-primary-400">
            {!! $this->helpHtml() !!}
        </article>
    @else
        {{-- Dokumenty prawne: regulamin / polityka prywatności / DPA --}}
        <div class="mt-4 flex items-center justify-between flex-wrap gap-2">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ $this->legalLastUpdatedLabel() }}: <strong>{{ $this->legalLastUpdated() }}</strong>
            </p>
            <div class="flex flex-wrap gap-2 text-xs">
                <a href="{{ route('legal.terms') }}" target="_blank" class="text-primary-600 hover:underline dark:text-primary-400">
                    {{ __('pages.help.legal.open_in_new_tab') }} →
                </a>
            </div>
        </div>

        @foreach ($this->legalDocuments() as $doc)
            <details class="group mt-3 rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900" {{ $loop->first ? 'open' : '' }}>
                <summary class="cursor-pointer list-none px-4 py-3 flex items-center justify-between gap-3 rounded-xl">
                    <span class="flex items-center gap-2">
                        @if ($doc['key'] === 'terms')
                            @svg('heroicon-o-document-text', 'h-5 w-5 text-primary-500')
                        @elseif ($doc['key'] === 'privacy')
                            @svg('heroicon-o-shield-check', 'h-5 w-5 text-primary-500')
                        @else
                            @svg('heroicon-o-document-duplicate', 'h-5 w-5 text-primary-500')
                        @endif
                        <span class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $doc['title'] }}</span>
                    </span>
                    @svg('heroicon-o-chevron-down', 'h-4 w-4 text-gray-400 transition group-open:rotate-180')
                </summary>
                <div class="border-t border-gray-200 px-4 py-4 dark:border-gray-700">
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $doc['intro'] }}</p>
                    <div class="mt-3 space-y-3">
                        @foreach ($doc['sections'] as $section)
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $section['heading'] }}</h4>
                                <p class="mt-1 text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $section['body'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </details>
        @endforeach
    @endif
</x-filament-panels::page>
