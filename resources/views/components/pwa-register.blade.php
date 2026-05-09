{{-- Service worker registration — wstawiane na końcu <body>.
     Filament panele dorzucają to przez render hook (BODY_END).
     Cicho ignorujemy błąd — brak SW ≠ broken page. --}}
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js').catch(e => console.warn('SW failed:', e));
        });
    }
</script>
