@php
    $isDemo = session('demo.is_demo') === true;
@endphp

@if ($isDemo)
    {{-- Wymusza jasny tryb dla demo niezależnie od OS prefers-color-scheme.
         Kilka warstw obrony:
         (a) <meta name="color-scheme"> — natychmiast (przed CSS) informuje
             browser, że UI ma być light. Działa dla scrollbarów, autofill,
             native form controls.
         (b) inline <style> z color-scheme: light only na <html> jako backup.
         (c) JS dodaje class `is-demo` do <html> — selektory
             `html:not(.is-demo)` w CSSach portalu nie odpalają dark mode.
         (d) JS usuwa Filamentową class `dark` (Tailwind dark-class strategy)
             i pilnuje MutationObserverem, żeby się nie wracała. --}}
    <meta name="color-scheme" content="light">
    <style>
        html.is-demo,
        html.is-demo body {
            color-scheme: light only !important;
        }
        html.is-demo input,
        html.is-demo select,
        html.is-demo textarea,
        html.is-demo button {
            color-scheme: light !important;
        }
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
