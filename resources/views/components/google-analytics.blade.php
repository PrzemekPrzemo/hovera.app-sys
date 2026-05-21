{{-- Google Analytics 4 (gtag.js) — globalny tracker dla całej aplikacji.
     Wstrzykiwany w <head> Filament paneli przez PanelsRenderHook::HEAD_END
     + publicznych Blade layoutów przez bezpośredni <x-google-analytics />.

     Config: config/hovera.php → analytics.google_id (env GOOGLE_ANALYTICS_ID).
     Pusty string = wyłączony (bezpieczny default dla local/testing env). --}}
@php($gaId = trim((string) config('hovera.analytics.google_id', '')))

@if ($gaId !== '' && ! app()->environment('testing'))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', @json($gaId));
    </script>
@endif
