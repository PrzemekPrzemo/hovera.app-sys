@php /** @var \App\Models\Central\Tenant $tenant */ @endphp
<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('embed.booking.title', ['tenant' => $tenant->name]) }}</title>
    <style>
        :root {
            --ochre: #A8956B;
            --ochre-dark: #8a7a55;
            --brown: #3D2E22;
            --brown-soft: #6b5b4a;
            --bg: #F7F4EF;
            --line: #E9E2D3;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: transparent; font-family: -apple-system, "Segoe UI", system-ui, sans-serif; color: var(--brown); }
        .card {
            padding: 2rem; background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px; box-shadow: 0 4px 18px rgba(168, 149, 107, 0.08);
            text-align: center;
        }
        h2 { margin: 0 0 .5rem; color: var(--brown); font-size: 1.4rem; font-weight: 700; }
        p { color: var(--brown-soft); line-height: 1.5; margin: .5rem 0 1rem; }
        .cta {
            display: inline-block;
            padding: .85rem 1.75rem; background: var(--ochre); color: #fff;
            text-decoration: none; border-radius: 10px; font-weight: 600; font-size: 1.05rem;
            transition: background .15s ease;
        }
        .cta:hover { background: var(--ochre-dark); }
    </style>
    <x-google-analytics />
</head>
<body>
    <div class="card">
        <h2>{{ __('embed.booking.heading', ['tenant' => $tenant->name]) }}</h2>
        <p>{{ $tagline ?? __('embed.booking.tagline_default') }}</p>
        <a class="cta" href="{{ url('/' . config('hovera.public_site.prefix', 's') . '/' . $tenant->slug . '/book') }}" target="_blank" rel="noopener">
            {{ __('embed.booking.cta') }}
        </a>
    </div>
</body>
</html>
