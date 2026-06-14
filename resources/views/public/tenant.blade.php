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
            --ochre: #A8956B;
            --ochre-dark: #8a7a55;
            --brown: #3D2E22;
            --brown-soft: #6b5b4a;
            --brown-faint: #9a8c7a;
            --bg: #F7F4EF;
            --line: #E9E2D3;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
            background: var(--bg);
            color: var(--brown);
            line-height: 1.6;
        }
        a { color: var(--ochre-dark); text-decoration: none; }
        a:hover { color: var(--brown); text-decoration: underline; }

        /* HERO — jasny, biały, z ochre akcentem zamiast solid primary background */
        header.hero {
            background: #fff;
            color: var(--brown);
            padding: 3rem 1.5rem 3.5rem;
            text-align: center;
            position: relative;
            border-bottom: 3px solid var(--ochre);
        }
        header.hero.with-image {
            color: #fff;
            background-size: cover;
            background-position: center;
            min-height: 320px;
            border-bottom: 0;
            box-shadow: inset 0 -3px 0 var(--ochre);
        }
        header.hero.with-image::before {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(180deg, rgba(61, 46, 34, .35) 0%, rgba(61, 46, 34, .75) 100%);
        }
        header.hero > * { position: relative; z-index: 1; }
        header.hero img.logo {
            max-height: 88px;
            max-width: 200px;
            margin-bottom: 1rem;
            background: #fff;
            border-radius: 8px;
            padding: 8px;
            border: 1px solid var(--line);
        }
        header.hero h1 {
            font-size: clamp(1.75rem, 4vw, 2.75rem);
            margin: 0 0 .5rem;
            font-weight: 700;
        }
        header.hero p.tagline {
            margin: 0;
            font-size: 1.05rem;
            opacity: .9;
            color: inherit;
        }

        /* Social — pillsy: cream tło, brown text, ochre na hover */
        ul.social {
            list-style: none; padding: 0; margin: 1.25rem 0 0;
            display: flex; justify-content: center; gap: .6rem; flex-wrap: wrap;
        }
        ul.social a {
            display: inline-flex; align-items: center; gap: .35rem;
            padding: .4rem .9rem;
            background: var(--bg); border: 1px solid var(--line);
            border-radius: 999px; color: var(--brown);
            text-decoration: none;
            font-size: .85rem; font-weight: 600;
            transition: background .15s, border-color .15s, color .15s;
        }
        ul.social a:hover {
            background: var(--ochre); border-color: var(--ochre);
            color: #fff; text-decoration: none;
        }
        header.hero.with-image ul.social a {
            background: rgba(255,255,255,.2); border-color: rgba(255,255,255,.35); color: #fff;
        }
        header.hero.with-image ul.social a:hover {
            background: #fff; border-color: #fff; color: var(--brown);
        }

        main {
            max-width: 760px;
            margin: -2rem auto 3rem;
            padding: 0 1.5rem;
        }
        section.card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 1.75rem 2rem;
            box-shadow: 0 4px 18px rgba(168, 149, 107, 0.08);
            margin-bottom: 1.25rem;
        }
        section.card h2 {
            margin: 0 0 .85rem;
            font-size: 1.15rem;
            color: var(--brown);
            font-weight: 700;
        }
        section.card .description {
            white-space: pre-line;
            color: var(--brown-soft);
        }

        dl.contact {
            display: grid;
            grid-template-columns: max-content 1fr;
            gap: .4rem 1rem;
            margin: 0;
            font-size: .95rem;
        }
        dl.contact dt {
            color: var(--brown-soft);
            font-weight: 600;
        }
        dl.contact dd {
            margin: 0;
            color: var(--brown);
        }

        .cta {
            display: inline-block;
            background: var(--ochre);
            color: #fff;
            padding: .7rem 1.4rem;
            border-radius: 8px;
            font-weight: 600;
            margin-top: .5rem;
            transition: background .15s ease;
        }
        .cta:hover { background: var(--ochre-dark); color: #fff; text-decoration: none; }

        .cta-secondary {
            display: inline-block;
            background: transparent;
            color: var(--ochre-dark);
            border: 1px solid var(--ochre);
            padding: .55rem 1.1rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: .9rem;
            margin-top: .5rem;
            transition: background .15s ease;
        }
        .cta-secondary:hover { background: var(--bg); color: var(--brown); text-decoration: none; }

        /* Box availability — pill cream + ochre border, jak embed widget */
        section.card.box-availability {
            display: flex; align-items: center; gap: 1.25rem;
            padding: 1.25rem 1.5rem;
        }
        .box-pill {
            display: grid; place-items: center;
            min-width: 72px; min-height: 72px;
            border-radius: 999px;
            font-weight: 800; font-size: 1.7rem;
            color: var(--brown);
            background: var(--bg);
            border: 3px solid var(--ochre);
        }
        .box-pill.box-full {
            color: var(--brown-faint);
            border-color: var(--line);
            background: #fafaf7;
        }
        .box-availability .body { flex: 1; }
        .box-availability strong { display: block; font-size: 1.05rem; margin-bottom: .15rem; color: var(--brown); }
        .box-availability .meta { display: block; color: var(--brown-soft); font-size: .85rem; }

        /* Instructors — cream cards z ochre dot */
        ul.instructors {
            list-style: none; padding: 0; margin: 0;
            display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: .5rem;
        }
        ul.instructors li {
            display: flex; align-items: center; gap: .65rem;
            padding: .6rem .85rem;
            background: var(--bg); border: 1px solid var(--line);
            border-radius: 8px;
            font-size: .95rem;
            font-weight: 500;
            color: var(--brown);
        }
        ul.instructors .dot {
            width: 10px; height: 10px; border-radius: 50%;
            display: inline-block; flex: 0 0 auto;
            background: var(--ochre);
        }

        /* Pricing table */
        section.card.pricing table { width: 100%; border-collapse: collapse; }
        section.card.pricing th {
            text-align: left; padding: .7rem .25rem;
            color: var(--brown-soft); font-weight: 600;
            font-size: .78rem; text-transform: uppercase; letter-spacing: .04em;
            border-bottom: 1px solid var(--line);
        }
        section.card.pricing td {
            padding: .75rem .25rem;
            border-bottom: 1px solid var(--line);
            font-size: .95rem;
            color: var(--brown);
        }
        section.card.pricing tr:last-child td { border-bottom: 0; }
        section.card.pricing td.price {
            text-align: right; font-weight: 700;
            color: var(--ochre-dark); white-space: nowrap;
        }
        section.card.pricing td.unit {
            text-align: right;
            color: var(--brown-soft);
            font-size: .85rem; white-space: nowrap;
        }

        footer.site-footer {
            text-align: center;
            color: var(--brown-faint);
            font-size: .8rem;
            padding: 1.5rem;
        }
        footer.site-footer a { color: var(--brown-soft); }
        footer.site-footer a:hover { color: var(--ochre-dark); }
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
            @php
                $inquiryUrl = url('/' . config('hovera.public_site.prefix', 's') . '/' . $tenant->slug . '/box-inquiry');
            @endphp
            <section class="card box-availability">
                @if ($box_availability['free'] > 0)
                    <div class="box-pill">{{ $box_availability['free'] }}</div>
                    <div class="body">
                        <strong>Mamy {{ $box_availability['free'] }}
                            {{ $box_availability['free'] === 1 ? 'wolny box' : ($box_availability['free'] < 5 ? 'wolne boksy' : 'wolnych boksów') }}
                            — czekamy na Ciebie!</strong>
                        <span class="meta">na {{ $box_availability['total'] }} łącznie</span>
                        <a class="cta-secondary" href="{{ $inquiryUrl }}">Zapytaj o boks →</a>
                    </div>
                @else
                    <div class="box-pill box-full">0</div>
                    <div class="body">
                        <strong>Wszystkie boksy są zajęte</strong>
                        <span class="meta">{{ $box_availability['total'] }} boksów · zostaw kontakt — wpiszemy na listę oczekujących</span>
                        <a class="cta-secondary" href="{{ $inquiryUrl }}">Wpisz się na listę →</a>
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
                            <span class="dot" @if (! empty($i['color'])) style="background: {{ $i['color'] }}" @endif></span>
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
