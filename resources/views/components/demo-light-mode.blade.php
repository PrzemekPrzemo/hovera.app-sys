@php
    $isDemo = session('demo.is_demo') === true;
@endphp

@if ($isDemo)
    {{-- Wymusza jasny tryb dla demo niezależnie od OS prefers-color-scheme.
         Dwa wektory: (a) dodaje class `is-demo` do <html> żeby selektory
         `html:not(.is-demo)` w stylach blokowały dark-mode bloki, (b)
         usuwa Filamentową class `dark` (Tailwind dark-class strategy) i
         pilnuje MutationObserverem żeby się nie wracała. --}}
    <style>
        html.is-demo { color-scheme: light only !important; }
    </style>
    <script>
        (function () {
            var html = document.documentElement;
            html.classList.add('is-demo');
            html.classList.remove('dark');
            try { localStorage.setItem('theme', 'light'); } catch (e) { /* private mode */ }
            try {
                var mo = new MutationObserver(function () {
                    if (html.classList.contains('dark')) {
                        html.classList.remove('dark');
                    }
                });
                mo.observe(html, { attributes: true, attributeFilter: ['class'] });
            } catch (e) { /* old browsers */ }
        })();
    </script>
@endif
