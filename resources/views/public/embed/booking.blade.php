@php /** @var \App\Models\Central\Tenant $tenant */ @endphp
<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('embed.booking.title', ['tenant' => $tenant->name]) }}</title>
    <style>
        :root { --primary: {{ $primary_color }}; }
        html, body { margin: 0; padding: 0; font-family: -apple-system, "Segoe UI", system-ui, sans-serif; }
        .card {
            padding: 2rem; background: #fff;
            border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,.06);
            text-align: center;
        }
        h2 { margin: 0 0 .5rem; color: #111827; font-size: 1.4rem; }
        p { color: #4b5563; line-height: 1.5; }
        .cta {
            display: inline-block; margin-top: 1rem;
            padding: .85rem 1.75rem; background: var(--primary); color: #fff;
            text-decoration: none; border-radius: 10px; font-weight: 600; font-size: 1.05rem;
        }
        .cta:hover { opacity: .92; }
        @media (prefers-color-scheme: dark) {
            .card { background: #1e293b; }
            h2 { color: #f3f4f6; }
            p { color: #cbd5e1; }
        }
    </style>
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
