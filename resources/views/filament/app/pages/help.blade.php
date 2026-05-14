@php /** @var \App\Filament\App\Pages\Help $this */ @endphp

<x-filament-panels::page>
    {{-- Pasek wyboru: widok (instrukcja / prawne) — segmentowany kontroler --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="inline-flex rounded-lg bg-gray-100 p-1 dark:bg-gray-800">
            <button
                type="button"
                wire:click="switchView('manual')"
                class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition
                       {{ $this->activeView === 'manual'
                            ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
                            : 'text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white' }}">
                @svg('heroicon-o-book-open', 'h-4 w-4')
                {{ __('pages.help.tab.manual') }}
            </button>
            <button
                type="button"
                wire:click="switchView('legal')"
                class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition
                       {{ $this->activeView === 'legal'
                            ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
                            : 'text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white' }}">
                @svg('heroicon-o-scale', 'h-4 w-4')
                {{ __('pages.help.tab.legal') }}
            </button>
        </div>

        @if ($this->activeView === 'legal')
            <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener" class="text-xs text-primary-600 hover:underline dark:text-primary-400">
                {{ __('pages.help.legal.open_in_new_tab') }} →
            </a>
        @endif
    </div>

    @if ($this->activeView === 'manual')
        {{-- Persona — kompaktowe chipy zamiast wielkich kart --}}
        <div class="mt-1 flex flex-wrap gap-1.5">
            @foreach ($this->personaCards() as $card)
                @php $active = $this->activePersona() === $card['key']; @endphp
                <button
                    type="button"
                    wire:click="switchPersona('{{ $card['key'] }}')"
                    title="{{ $card['description'] }}"
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium transition border
                           {{ $active
                                ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                                : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800' }}">
                    @svg($card['icon'], 'h-4 w-4')
                    {{ $card['label'] }}
                </button>
            @endforeach
        </div>

        {{-- Treść instrukcji --}}
        <article class="prose prose-sm max-w-3xl dark:prose-invert mt-4
                    prose-headings:text-gray-900 dark:prose-headings:text-gray-100
                    prose-h1:text-xl prose-h1:font-bold prose-h1:mb-3 prose-h1:mt-0
                    prose-h2:text-lg prose-h2:font-semibold prose-h2:mt-5 prose-h2:mb-2
                    prose-h3:text-base prose-h3:font-semibold prose-h3:mt-3 prose-h3:mb-1.5
                    prose-p:leading-relaxed prose-p:my-2 prose-p:text-sm
                    prose-li:my-0.5 prose-li:text-sm
                    prose-table:my-3 prose-table:text-sm
                    prose-th:bg-gray-50 dark:prose-th:bg-gray-800 prose-th:px-2.5 prose-th:py-1.5
                    prose-td:px-2.5 prose-td:py-1.5 prose-td:border-t prose-td:border-gray-200 dark:prose-td:border-gray-700
                    prose-code:bg-gray-100 dark:prose-code:bg-gray-800 prose-code:px-1 prose-code:py-0.5 prose-code:rounded prose-code:text-xs prose-code:before:content-none prose-code:after:content-none
                    prose-pre:bg-gray-900 prose-pre:text-gray-100 prose-pre:p-3 prose-pre:rounded-lg prose-pre:text-xs
                    prose-strong:font-semibold prose-strong:text-gray-900 dark:prose-strong:text-gray-100
                    prose-blockquote:border-l-4 prose-blockquote:border-primary-500 prose-blockquote:bg-primary-50 dark:prose-blockquote:bg-primary-900/20 prose-blockquote:py-1.5 prose-blockquote:px-3 prose-blockquote:rounded-r prose-blockquote:not-italic prose-blockquote:text-sm
                    prose-a:text-primary-600 dark:prose-a:text-primary-400 prose-a:no-underline hover:prose-a:underline
                    prose-hr:my-4">
            {!! $this->helpHtml() !!}
        </article>
    @else
        {{-- Dokumenty prawne — accordion, kompaktowy --}}
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            {{ $this->legalLastUpdatedLabel() }}: <strong class="font-semibold">{{ $this->legalLastUpdated() }}</strong>
        </p>

        <div class="mt-2 max-w-3xl space-y-2">
            @foreach ($this->legalDocuments() as $doc)
                <details class="group rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900" {{ $loop->first ? 'open' : '' }}>
                    <summary class="cursor-pointer list-none px-3 py-2.5 flex items-center justify-between gap-3">
                        <span class="flex items-center gap-2">
                            @if ($doc['key'] === 'terms')
                                @svg('heroicon-o-document-text', 'h-4 w-4 text-primary-500')
                            @elseif ($doc['key'] === 'privacy')
                                @svg('heroicon-o-shield-check', 'h-4 w-4 text-primary-500')
                            @else
                                @svg('heroicon-o-document-duplicate', 'h-4 w-4 text-primary-500')
                            @endif
                            <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $doc['title'] }}</span>
                        </span>
                        @svg('heroicon-o-chevron-down', 'h-4 w-4 text-gray-400 transition group-open:rotate-180')
                    </summary>
                    <div class="border-t border-gray-200 px-3 py-3 dark:border-gray-700">
                        <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">{{ $doc['intro'] }}</p>
                        <div class="mt-2 space-y-2">
                            @foreach ($doc['sections'] as $section)
                                <div>
                                    <h4 class="text-xs font-semibold text-gray-900 dark:text-gray-100">{{ $section['heading'] }}</h4>
                                    <p class="mt-0.5 text-xs leading-relaxed text-gray-700 dark:text-gray-300">{{ $section['body'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </details>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
