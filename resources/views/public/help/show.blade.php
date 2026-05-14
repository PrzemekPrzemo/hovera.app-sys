@php
    /** @var string $activeView 'manual'|'legal' */
    /** @var string $activePersona */
    /** @var string|null $helpHtml */
    /** @var array<int, array{key:string,label:string,description:string,icon:string}> $personas */
    /** @var array|null $legalDocuments */
    /** @var string|null $singleDoc */
    /** @var string $pageTitle */
    $lastUpdated = __('public/legal.last_updated');
    $lastUpdatedLabel = __('public/legal.last_updated_label');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }} — hovera {{ __('pages.help.title') }}</title>
    <meta name="robots" content="index, follow">
    <meta name="description" content="{{ __('pages.help.public_meta_desc') }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <style>
        :root {
            --primary: #A8956B;
            --primary-dark: #8F8576;
            --bg: #F7F4EF;
            --card: #FFFFFF;
            --text: #1F1A17;
            --text-soft: #4a3f33;
            --brown: #3D2E22;
            --muted: #6b7280;
            --border: #e5dfd0;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--text); line-height: 1.6; }
        a { color: var(--primary); text-decoration: none; }
        a:hover { text-decoration: underline; }
        header.site { background: #fff; border-bottom: 1px solid var(--border); padding: 1rem 1.5rem; position: sticky; top: 0; z-index: 10; }
        header.site .inner { max-width: 1080px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .logo { font-size: 1.25rem; font-weight: 700; letter-spacing: -.02em; color: var(--brown); }
        nav.main a { margin-left: 1.25rem; color: var(--brown); font-weight: 500; font-size: .92rem; }
        nav.main a.cta { background: var(--primary); color: #fff; padding: .5rem .9rem; border-radius: 8px; }
        nav.main a.cta:hover { filter: brightness(.95); text-decoration: none; }

        main.help { max-width: 1080px; margin: 2rem auto 4rem; padding: 0 1.5rem; }
        .hero { background: linear-gradient(135deg, #fff 0%, #f4ecdc 100%); border: 1px solid var(--border); border-radius: 16px; padding: 1.5rem 2rem; margin-bottom: 1.5rem; }
        .hero h1 { margin: 0 0 .35rem; font-size: 1.75rem; color: var(--brown); letter-spacing: -.01em; }
        .hero p { margin: 0; color: var(--text-soft); font-size: 1rem; }

        .toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; margin-bottom: 1rem; }
        .seg { display: inline-flex; background: #ece4d3; padding: 4px; border-radius: 10px; }
        .seg a { padding: .45rem .9rem; font-size: .9rem; font-weight: 500; color: var(--brown); border-radius: 7px; display: inline-flex; align-items: center; gap: .35rem; }
        .seg a:hover { background: rgba(255,255,255,.5); text-decoration: none; }
        .seg a.active { background: #fff; color: var(--brown); box-shadow: 0 1px 2px rgba(0,0,0,.05); }
        .seg a.active:hover { background: #fff; }
        .seg svg { width: 14px; height: 14px; }

        .chips { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: 1.25rem; }
        .chips a { display: inline-flex; align-items: center; gap: .4rem; padding: .4rem .85rem; border: 1px solid var(--border); border-radius: 999px; background: #fff; color: var(--text-soft); font-size: .82rem; font-weight: 500; }
        .chips a:hover { border-color: var(--primary-dark); text-decoration: none; }
        .chips a.active { border-color: var(--primary); background: rgba(168,149,107,.15); color: var(--brown); }
        .chips svg { width: 14px; height: 14px; }

        .card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 1.75rem 2rem; }
        .card.legal-doc { padding: 0; margin-bottom: .75rem; overflow: hidden; }
        .card.legal-doc summary { padding: 1rem 1.25rem; cursor: pointer; list-style: none; display: flex; justify-content: space-between; align-items: center; gap: 1rem; font-size: .95rem; font-weight: 600; color: var(--brown); }
        .card.legal-doc summary::-webkit-details-marker { display: none; }
        .card.legal-doc .legal-body { padding: 0 1.25rem 1.25rem; border-top: 1px solid var(--border); }
        .card.legal-doc .legal-body .intro { color: var(--text-soft); font-size: .92rem; margin: 1rem 0 1.25rem; }
        .card.legal-doc h3 { font-size: .98rem; color: var(--brown); margin: 1.1rem 0 .35rem; }
        .card.legal-doc p { margin: .35rem 0 .9rem; color: var(--text-soft); font-size: .9rem; }
        .summary-chev { transition: transform .2s; }
        details[open] .summary-chev { transform: rotate(180deg); }

        .meta { color: var(--muted); font-size: .85rem; margin-bottom: 1rem; }

        /* Markdown content */
        .prose h1 { font-size: 1.5rem; margin: 0 0 1rem; color: var(--brown); letter-spacing: -.01em; }
        .prose h2 { font-size: 1.15rem; margin: 1.5rem 0 .5rem; color: var(--brown); }
        .prose h3 { font-size: 1rem; margin: 1rem 0 .35rem; }
        .prose p, .prose li { color: var(--text-soft); }
        .prose ul, .prose ol { padding-left: 1.25rem; }
        .prose li { margin: .25rem 0; }
        .prose table { width: 100%; border-collapse: collapse; margin: 1rem 0; font-size: .92rem; }
        .prose th { background: #f4ecdc; padding: .55rem .75rem; text-align: left; font-weight: 600; }
        .prose td { padding: .55rem .75rem; border-top: 1px solid var(--border); }
        .prose code { background: #f4ecdc; padding: .15rem .4rem; border-radius: 4px; font-family: ui-monospace, monospace; font-size: .88em; }
        .prose pre { background: #3D2E22; color: #f3f4f6; padding: 1rem; border-radius: 8px; overflow-x: auto; }
        .prose blockquote { border-left: 4px solid var(--primary); background: rgba(168,149,107,.08); padding: .65rem 1rem; margin: 1rem 0; border-radius: 0 6px 6px 0; }
        .prose hr { border: none; border-top: 1px solid var(--border); margin: 1.5rem 0; }
        .prose strong { color: var(--brown); }
        .prose a { color: var(--primary); text-decoration: underline; }

        .cta-banner { margin-top: 2rem; background: var(--brown); color: #fff; border-radius: 14px; padding: 1.5rem 2rem; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem; }
        .cta-banner p { margin: 0; font-size: 1rem; }
        .cta-banner a { background: var(--primary); color: #fff; padding: .6rem 1.1rem; border-radius: 8px; font-weight: 600; }
        .cta-banner a:hover { filter: brightness(1.1); text-decoration: none; }

        footer.site { border-top: 1px solid var(--border); padding: 1.5rem; text-align: center; color: var(--muted); font-size: .85rem; }
        footer.site a { color: var(--muted); }
        footer.site .row + .row { margin-top: .35rem; }

        @media (prefers-color-scheme: dark) {
            html, body { background: #1F1A17; color: #F7F4EF; }
            header.site, footer.site { background: #2a221c; border-color: #4a3d31; }
            .hero { background: linear-gradient(135deg, #2a221c 0%, #3a2e22 100%); border-color: #4a3d31; }
            .hero h1, .logo, nav.main a, .seg a, .chips a.active, .card.legal-doc summary, .card.legal-doc h3, .prose h1, .prose h2, .prose h3, .prose strong { color: #E9E2D3; }
            .hero p, .prose p, .prose li, .card.legal-doc p, .card.legal-doc .legal-body .intro { color: #C8B8A4; }
            .seg { background: #3a2e22; }
            .seg a.active { background: #4a3d31; color: #fff; }
            .chips a { background: #2a221c; border-color: #4a3d31; color: #C8B8A4; }
            .chips a.active { background: rgba(168,149,107,.25); border-color: var(--primary); }
            .card { background: #2a221c; border-color: #4a3d31; }
            .prose code, .prose th { background: #3a2e22; }
            .prose td, .card.legal-doc .legal-body { border-color: #4a3d31; }
            .meta, footer.site, footer.site a { color: #C8B8A4; }
        }

        @media (max-width: 640px) {
            .hero { padding: 1.25rem 1.25rem; }
            .hero h1 { font-size: 1.4rem; }
            .card { padding: 1.25rem 1rem; }
            main.help { padding: 0 1rem; margin: 1rem auto 3rem; }
        }
    </style>
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

    <main class="help">
        <div class="hero">
            <h1>{{ __('pages.help.title') }}</h1>
            <p>{{ __('pages.help.public_lead') }}</p>
        </div>

        <div class="toolbar">
            <div class="seg">
                <a href="{{ route('help.show', ['persona' => $activePersona]) }}" @class(['active' => $activeView === 'manual'])>
                    {{-- book-open icon --}}
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"/></svg>
                    {{ __('pages.help.tab.manual') }}
                </a>
                <a href="{{ route('help.legal') }}" @class(['active' => $activeView === 'legal'])>
                    {{-- scale icon --}}
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0 0 12 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 0 1-2.031.352 5.988 5.988 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.97ZM2.624 5.49c.965-.203 1.974-.377 3-.52M3.75 4.97l2.621 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 0 1-2.031.352 5.989 5.989 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L1.5 4.97Z"/></svg>
                    {{ __('pages.help.tab.legal') }}
                </a>
            </div>

            <span class="meta">{{ $lastUpdatedLabel }}: <strong>{{ $lastUpdated }}</strong></span>
        </div>

        @if ($activeView === 'manual')
            <nav class="chips" aria-label="{{ __('pages.help.tab.manual') }}">
                @foreach ($personas as $p)
                    <a href="{{ route('help.show', ['persona' => $p['key']]) }}"
                       @class(['active' => $activePersona === $p['key']])
                       title="{{ $p['description'] }}">
                        @switch($p['icon'])
                            @case('storefront')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72L4.318 3.44A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z"/></svg>
                                @break
                            @case('users')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/></svg>
                                @break
                            @case('heart')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/></svg>
                                @break
                            @default
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z"/></svg>
                        @endswitch
                        {{ $p['label'] }}
                    </a>
                @endforeach
            </nav>

            <article class="card prose">
                {!! $helpHtml !!}
            </article>
        @else
            @foreach ($legalDocuments as $doc)
                @php $isOpen = ($singleDoc === null && $loop->first) || ($singleDoc === $doc['key']); @endphp
                <details class="card legal-doc" id="{{ $doc['key'] }}" {{ $isOpen ? 'open' : '' }}>
                    <summary>
                        <span>{{ $doc['title'] }}</span>
                        <svg class="summary-chev" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                    </summary>
                    <div class="legal-body">
                        <p class="intro">{{ $doc['intro'] }}</p>
                        @foreach ($doc['sections'] as $section)
                            <h3>{{ $section['heading'] }}</h3>
                            <p>{{ $section['body'] }}</p>
                        @endforeach
                    </div>
                </details>
            @endforeach
        @endif

        <div class="cta-banner">
            <p>{{ __('pages.help.public_cta') }}</p>
            <a href="{{ route('signup.show') }}">{{ __('public/legal.nav.signup') }} →</a>
        </div>
    </main>

    <footer class="site">
        <div class="row">{{ __('public/legal.footer.support') }}</div>
        <div class="row">
            <a href="{{ route('help.show') }}">{{ __('pages.help.navigation') }}</a>
            ·
            <a href="{{ route('legal.terms') }}">{{ __('public/legal.nav.terms') }}</a>
            ·
            <a href="{{ route('legal.privacy') }}">{{ __('public/legal.nav.privacy') }}</a>
            ·
            <a href="{{ route('legal.dpa') }}">{{ __('public/legal.nav.dpa') }}</a>
        </div>
        <div class="row">{{ __('public/legal.footer.copyright', ['year' => date('Y')]) }}</div>
    </footer>
</body>
</html>
