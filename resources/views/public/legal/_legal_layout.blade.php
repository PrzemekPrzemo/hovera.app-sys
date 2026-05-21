{{--
    Wspólny shell dla stron prawnych (regulamin / privacy / DPA).

    @param string $title              tytuł strony (h1 + <title>)
    @param string $intro              akapit wstępny pod h1
    @param array  $sections           [['heading' => ..., 'body' => ...], ...]
    @param string $active             slug aktywnej zakładki (terms|privacy|dpa)
--}}
@php
    /** @var string $title */
    /** @var string $intro */
    /** @var array<int, array{heading: string, body: string}> $sections */
    /** @var string $active */
    $lastUpdated = __('public/legal.last_updated');
    $lastUpdatedLabel = __('public/legal.last_updated_label');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — hovera</title>
    <meta name="robots" content="index, follow">
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <style>
        :root {
            --primary: #A8956B;
            --primary-dark: #8F8576;
            --bg: #F7F4EF;
            --text: #1F1A17;
            --brown: #3D2E22;
            --muted: #6b7280;
            --border: #e5dfd0;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--text); line-height: 1.6; }
        a { color: var(--primary); text-decoration: none; }
        a:hover { text-decoration: underline; }
        header.site { background: #fff; border-bottom: 1px solid var(--border); padding: 1rem 1.5rem; position: sticky; top: 0; z-index: 10; }
        header.site .inner { max-width: 960px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .logo { font-size: 1.25rem; font-weight: 700; letter-spacing: -.02em; color: var(--brown); }
        nav.main a { margin-left: 1.25rem; color: var(--brown); font-weight: 500; font-size: .92rem; }
        nav.main a.cta { background: var(--primary); color: #fff; padding: .5rem .9rem; border-radius: 8px; }
        nav.main a.cta:hover { filter: brightness(.95); text-decoration: none; }
        main.legal { max-width: 760px; margin: 2.5rem auto 4rem; padding: 0 1.5rem; }
        .meta { color: var(--muted); font-size: .85rem; margin-bottom: 1rem; }
        h1 { font-size: 2rem; margin: 0 0 .75rem; color: var(--brown); letter-spacing: -.01em; }
        h2 { font-size: 1.25rem; margin: 2rem 0 .5rem; color: var(--brown); }
        p { margin: .5rem 0 1rem; }
        .intro { font-size: 1.02rem; color: var(--text); margin-bottom: 1.5rem; }
        .legal-tabs { display: flex; gap: .5rem; margin: 1.5rem 0 2rem; flex-wrap: wrap; }
        .legal-tabs a { padding: .5rem 1rem; border: 1px solid var(--border); border-radius: 999px; background: #fff; color: var(--brown); font-size: .88rem; }
        .legal-tabs a:hover { border-color: var(--primary); text-decoration: none; }
        .legal-tabs a.active { background: var(--primary); border-color: var(--primary); color: #fff; }
        footer.site { border-top: 1px solid var(--border); padding: 1.5rem; text-align: center; color: var(--muted); font-size: .85rem; }
        footer.site a { color: var(--muted); }
        footer.site .row + .row { margin-top: .35rem; }
        @media (prefers-color-scheme: dark) {
            html, body { background: #1F1A17; color: #F7F4EF; }
            header.site, footer.site { background: #2a221c; border-color: #4a3d31; }
            .legal-tabs a { background: #2a221c; border-color: #4a3d31; color: #E9E2D3; }
            .logo, nav.main a, h1, h2 { color: #E9E2D3; }
            .meta, footer.site, footer.site a { color: #C8B8A4; }
        }
        /* Mobile (≤600px) — kompaktowy header (nav.main ukryty oprócz CTA),
           mniej paddingu w main, mniejsze h1. */
        @media (max-width: 600px) {
            header.site { padding: .75rem 1rem; }
            nav.main a { margin-left: .5rem; font-size: .85rem; }
            nav.main a:not(.cta) { display: none; }
            main.legal { margin: 1.5rem auto 2.5rem; padding: 0 1rem; }
            h1 { font-size: 1.55rem; }
            h2 { font-size: 1.1rem; margin: 1.5rem 0 .35rem; }
            .legal-tabs { gap: .35rem; }
            .legal-tabs a { padding: .4rem .8rem; font-size: .82rem; }
            footer.site { padding: 1.25rem 1rem; }
        }
    </style>
    <x-google-analytics />
</head>
<body>
    <header class="site">
        <div class="inner">
            <a href="/" class="logo">hovera</a>
            <nav class="main">
                <a href="/pricing">{{ __('public/legal.nav.pricing') }}</a>
                <a href="{{ route('help.show') }}">{{ __('pages.help.navigation') }}</a>
                <a href="{{ route('demo.login') }}">{{ __('public/legal.nav.demo') }}</a>
                <a href="{{ route('signup.show') }}" class="cta">{{ __('public/legal.nav.signup') }}</a>
            </nav>
        </div>
    </header>

    <main class="legal">
        <nav class="legal-tabs" aria-label="Legal pages">
            <a href="{{ route('legal.terms') }}" @class(['active' => $active === 'terms'])>{{ __('public/legal.nav.terms') }}</a>
            <a href="{{ route('legal.privacy') }}" @class(['active' => $active === 'privacy'])>{{ __('public/legal.nav.privacy') }}</a>
            <a href="{{ route('legal.dpa') }}" @class(['active' => $active === 'dpa'])>{{ __('public/legal.nav.dpa') }}</a>
            <a href="{{ route('legal.marketplace') }}" @class(['active' => $active === 'marketplace'])>{{ __('public/legal.nav.marketplace') }}</a>
        </nav>

        <div class="meta">{{ $lastUpdatedLabel }}: {{ $lastUpdated }}</div>
        <h1>{{ $title }}</h1>
        {{-- Intro i body mogą zawierać <a href="..."> z lang files (kontrolowane
             przez Operatora, nie przez użytkownika). Renderujemy raw HTML. --}}
        <p class="intro">{!! $intro !!}</p>

        @foreach ($sections as $section)
            <h2>{{ $section['heading'] }}</h2>
            <p>{!! $section['body'] !!}</p>
        @endforeach
    </main>

    <footer class="site">
        <div class="row">{{ __('public/legal.footer.support') }}</div>
        <div class="row">
            <a href="{{ route('legal.terms') }}">{{ __('public/legal.nav.terms') }}</a>
            ·
            <a href="{{ route('legal.privacy') }}">{{ __('public/legal.nav.privacy') }}</a>
            ·
            <a href="{{ route('legal.dpa') }}">{{ __('public/legal.nav.dpa') }}</a>
            ·
            <a href="{{ route('legal.marketplace') }}">{{ __('public/legal.nav.marketplace') }}</a>
        </div>
        <div class="row">{{ __('public/legal.footer.copyright', ['year' => date('Y')]) }}</div>
    </footer>
</body>
</html>
