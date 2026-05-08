@php /** @var \App\Filament\App\Pages\Help $this */ @endphp

<x-filament-panels::page>
    <div class="prose prose-sm max-w-none dark:prose-invert
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
    </div>
</x-filament-panels::page>
