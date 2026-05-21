<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tenant->name }} — Hovera</title>

    <meta name="description" content="{{ Str::limit($description ?? "{$tenant->name} — stajnia jeździecka.", 160) }}">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ route('public.tenant', ['slug' => $tenant->slug]) }}">

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
            color: #3a2f25;
            line-height: 1.6;
        }
        a { color: var(--primary); text-decoration: none; }
        a:hover { text-decoration: underline; }

        header.hero {
            background: var(--primary);
            color: #fff;
            padding: 3rem 1.5rem 4rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        header.hero.with-image {
            background-size: cover;
            background-position: center;
            min-height: 320px;
        }
        header.hero.with-image::before {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(180deg, rgba(0,0,0,.25) 0%, rgba(0,0,0,.55) 100%);
        }
        header.hero > * { position: relative; z-index: 1; }
        header.hero img.logo {
            max-height: 88px;
            max-width: 200px;
            margin-bottom: 1rem;
            background: #fff;
            border-radius: 8px;
            padding: 8px;
        }
        ul.social {
            list-style: none; padding: 0; margin: 1.25rem 0 0;
            display: flex; justify-content: center; gap: .75rem; flex-wrap: wrap;
        }
        ul.social a {
            display: inline-flex; align-items: center; gap: .35rem;
            padding: .4rem .9rem; background: rgba(255,255,255,.18);
            border-radius: 999px; color: #fff; text-decoration: none;
            font-size: .85rem; font-weight: 500;
            transition: background .15s;
        }
        ul.social a:hover { background: rgba(255,255,255,.3); text-decoration: none; }

        section.card.pricing table { width: 100%; border-collapse: collapse; }
        section.card.pricing th { text-align: left; padding: .5rem .25rem; color: #6b7280; font-weight: 500; font-size: .8rem; text-transform: uppercase; letter-spacing: .03em; border-bottom: 1px solid #f7f4ef; }
        section.card.pricing td { padding: .65rem .25rem; border-bottom: 1px solid #f3f4f6; font-size: .95rem; }
        section.card.pricing td.price { text-align: right; font-weight: 600; color: var(--primary); white-space: nowrap; }
        section.card.pricing td.unit { text-align: right; color: #6b7280; font-size: .85rem; white-space: nowrap; }
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
            body { background: #2a2017; color: #f7f4ef; }
            section.card { background: #3a2f25; }
            section.card h2 { color: #f3f4f6; }
            section.card .description { color: #e9e2d3; }
            dl.contact dt { color: #c8b8a4; }
            dl.contact dd { color: #f3f4f6; }
            footer.site-footer a { color: #c8b8a4; }
        }
    </style>
    <x-google-analytics />
</head>
<body>
    <header class="hero @if ($hero_image_url ?? null) with-image @endif"
            @if ($hero_image_url ?? null) style="background-image: url('{{ $hero_image_url }}')" @endif>
        @if ($logo_url)
            <img src="{{ $logo_url }}" alt="Logo {{ $tenant->name }}" class="logo">
        @endif
        <h1>{{ $tenant->name }}</h1>
        <p class="tagline">{{ $tagline ?? 'Stajnia jeździecka' }}</p>

        @if (array_filter($social ?? []))
            <ul class="social">
                @if ($social['facebook'] ?? null)
                    <li><a href="{{ $social['facebook'] }}" target="_blank" rel="noopener">Facebook</a></li>
                @endif
                @if ($social['instagram'] ?? null)
                    <li><a href="{{ $social['instagram'] }}" target="_blank" rel="noopener">Instagram</a></li>
                @endif
                @if ($social['youtube'] ?? null)
                    <li><a href="{{ $social['youtube'] }}" target="_blank" rel="noopener">YouTube</a></li>
                @endif
                @if ($social['tiktok'] ?? null)
                    <li><a href="{{ $social['tiktok'] }}" target="_blank" rel="noopener">TikTok</a></li>
                @endif
            </ul>
        @endif
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

        @if (! empty($pricing))
            <section class="card pricing">
                <h2>Cennik pensjonatu</h2>
                <table>
                    <thead>
                        <tr><th>Pozycja</th><th></th><th>Cena</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($pricing as $p)
                            <tr>
                                <td>{{ $p['name'] }}</td>
                                <td class="unit">{{ $p['unit'] }} / {{ $p['frequency'] }}</td>
                                <td class="price">{{ $p['price_pln'] }} zł</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
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
