{{-- PWA meta tags — manifest, theme color, Apple touch icon.
     Include via <x-pwa-head /> wewnątrz <head> publicznych Blade'ów.
     Filament panele dorzucają to przez render hook (HEAD_END). --}}
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#A8956B">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="hovera">
<link rel="apple-touch-icon" href="/img/pwa/apple-touch-icon.png">
