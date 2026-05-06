<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tenant->name }} — Hovera</title>

    <meta name="description" content="{{ Str::limit($description ?? "{$tenant->name} — stajnia jeździecka.", 160) }}">

    {{-- Open Graph --}}
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $tenant->name }}">
    <meta property="og:description" content="{{ Str::limit($description ?? "{$tenant->name} — stajnia jeździecka.", 200) }}">
    <meta property="og:url" content="{{ url('/s/' . $tenant->slug) }}">
    @if ($logo_url)
        <meta property="og:image" content="{{ $logo_url }}">
    @endif

    <style>
        :root {
            --primary: {{ $primary_color }};
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
            background: #fafafa;
            color: #1f2937;
            line-height: 1.6;
        }
        a { color: var(--primary); text-decoration: none; }
        a:hover { text-decoration: underline; }

        header.hero {
            background: var(--primary);
            color: #fff;
            padding: 3rem 1.5rem 4rem;
            text-align: center;
        }
        header.hero img.logo {
            max-height: 88px;
            max-width: 200px;
            margin-bottom: 1rem;
            background: #fff;
            border-radius: 8px;
            padding: 8px;
        }
        header.hero h1 {
            font-size: clamp(1.75rem, 4vw, 2.75rem);
            margin: 0 0 .5rem;
            font-weight: 700;
        }
        header.hero p.tagline {
            margin: 0;
            font-size: 1.05rem;
            opacity: .92;
        }

        main {
            max-width: 760px;
            margin: -2rem auto 3rem;
            padding: 0 1.5rem;
        }
        section.card {
            background: #fff;
            border-radius: 12px;
            padding: 1.75rem 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,.06);
            margin-bottom: 1.25rem;
        }
        section.card h2 {
            margin: 0 0 .85rem;
            font-size: 1.15rem;
            color: #111827;
        }
        section.card .description {
            white-space: pre-line;
            color: #374151;
        }

        dl.contact {
            display: grid;
            grid-template-columns: max-content 1fr;
            gap: .35rem 1rem;
            margin: 0;
            font-size: .95rem;
        }
        dl.contact dt {
            color: #6b7280;
            font-weight: 500;
        }
        dl.contact dd {
            margin: 0;
            color: #111827;
        }

        .cta {
            display: inline-block;
            background: var(--primary);
            color: #fff;
            padding: .7rem 1.4rem;
            border-radius: 8px;
            font-weight: 600;
            margin-top: .5rem;
        }
        .cta:hover { opacity: .92; text-decoration: none; }

        .soon {
            display: inline-block;
            color: #6b7280;
            font-size: .85rem;
            margin-left: .5rem;
        }

        footer.site-footer {
            text-align: center;
            color: #9ca3af;
            font-size: .8rem;
            padding: 1.5rem;
        }
        footer.site-footer a { color: #6b7280; }

        @media (prefers-color-scheme: dark) {
            body { background: #0f172a; color: #e5e7eb; }
            section.card { background: #1e293b; }
            section.card h2 { color: #f3f4f6; }
            section.card .description { color: #cbd5e1; }
            dl.contact dt { color: #94a3b8; }
            dl.contact dd { color: #f3f4f6; }
            footer.site-footer a { color: #94a3b8; }
        }
    </style>
</head>
<body>
    <header class="hero">
        @if ($logo_url)
            <img src="{{ $logo_url }}" alt="Logo {{ $tenant->name }}" class="logo">
        @endif
        <h1>{{ $tenant->name }}</h1>
        <p class="tagline">Stajnia jeździecka</p>
    </header>

    <main>
        @if ($description)
            <section class="card">
                <h2>O stajni</h2>
                <p class="description">{{ $description }}</p>
            </section>
        @endif

        @if ($contact_email || $contact_phone || $address || $website)
            <section class="card">
                <h2>Kontakt</h2>
                <dl class="contact">
                    @if ($contact_phone)
                        <dt>Telefon</dt>
                        <dd><a href="tel:{{ $contact_phone }}">{{ $contact_phone }}</a></dd>
                    @endif
                    @if ($contact_email)
                        <dt>Email</dt>
                        <dd><a href="mailto:{{ $contact_email }}">{{ $contact_email }}</a></dd>
                    @endif
                    @if ($address)
                        <dt>Adres</dt>
                        <dd>{{ $address }}</dd>
                    @endif
                    @if ($website)
                        <dt>WWW</dt>
                        <dd><a href="{{ $website }}" rel="noopener" target="_blank">{{ $website }}</a></dd>
                    @endif
                </dl>
            </section>
        @endif

        <section class="card">
            <h2>Zapisy na lekcje</h2>
            <p>Online booking będzie dostępny wkrótce. Tymczasowo zapisz się przez kontakt powyżej.</p>
            <span class="soon">— Wkrótce</span>
        </section>
    </main>

    <footer class="site-footer">
        Strona stajni · powered by <a href="https://hovera.app" rel="noopener">Hovera</a>
    </footer>
</body>
</html>
