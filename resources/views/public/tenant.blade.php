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

        section.card.box-availability {
            display: flex; align-items: center; gap: 1rem;
            padding: 1.25rem;
        }
        .box-pill {
            display: grid; place-items: center;
            min-width: 64px; min-height: 64px;
            border-radius: 999px;
            font-weight: 800; font-size: 1.5rem;
            color: #fff;
        }
        .box-pill.box-free { background: var(--primary); }
        .box-pill.box-full { background: #6b7280; }
        .box-availability strong { display: block; font-size: 1.05rem; margin-bottom: .15rem; }
        .box-availability .meta { display: block; color: #6b7280; font-size: .85rem; }

        ul.instructors {
            list-style: none; padding: 0; margin: 0;
            display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: .5rem;
        }
        ul.instructors li {
            display: flex; align-items: center; gap: .6rem;
            padding: .5rem .75rem;
            background: #f9fafb;
            border-radius: 8px;
            font-size: .95rem;
        }
        ul.instructors .dot {
            width: 10px; height: 10px; border-radius: 50%;
            display: inline-block; flex: 0 0 auto;
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
        <p class="tagline">{{ $tagline ?? 'Stajnia jeździecka' }}</p>
    </header>

    <main>
        @if (! empty($box_availability))
            <section class="card box-availability">
                @if ($box_availability['free'] > 0)
                    <div class="box-pill box-free">{{ $box_availability['free'] }}</div>
                    <div>
                        <strong>Mamy {{ $box_availability['free'] }}
                            {{ $box_availability['free'] === 1 ? 'wolny box' : ($box_availability['free'] < 5 ? 'wolne boksy' : 'wolnych boksów') }}
                            — czekamy na Ciebie!</strong>
                        <span class="meta">na {{ $box_availability['total'] }} łącznie · skontaktuj się ze stajnią</span>
                    </div>
                @else
                    <div class="box-pill box-full">0</div>
                    <div>
                        <strong>Wszystkie boksy są zajęte</strong>
                        <span class="meta">{{ $box_availability['total'] }} boksów · zostaw kontakt — wpiszemy na listę oczekujących</span>
                    </div>
                @endif
            </section>
        @endif

        @if ($description)
            <section class="card">
                <h2>O stajni</h2>
                <p class="description">{{ $description }}</p>
            </section>
        @endif

        @if ($contact_email || $contact_phone || $address || $website || ($opening_hours ?? null))
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
                    @if (! empty($opening_hours))
                        <dt>Godziny</dt>
                        <dd>{{ $opening_hours }}</dd>
                    @endif
                </dl>
            </section>
        @endif

        @if (! empty($instructors))
            <section class="card">
                <h2>Nasi instruktorzy</h2>
                <ul class="instructors">
                    @foreach ($instructors as $i)
                        <li>
                            <span class="dot" style="background: {{ $i['color'] }}"></span>
                            <span>{{ $i['name'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="card">
            <h2>Zapisy na lekcje</h2>
            <p>Wybierz instruktora, datę i terminy w naszym systemie online.</p>
            <a class="cta" href="{{ url('/' . config('hovera.public_site.prefix', 's') . '/' . $tenant->slug . '/book') }}">Zapisz się online →</a>
        </section>
    </main>

    <footer class="site-footer">
        Strona stajni · powered by <a href="https://hovera.app" rel="noopener">Hovera</a>
    </footer>
</body>
</html>
