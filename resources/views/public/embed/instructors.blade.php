@php /** @var \App\Models\Central\Tenant $tenant */ @endphp
<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('embed.instructors.title', ['tenant' => $tenant->name]) }}</title>
    <style>
        :root { --primary: {{ $primary_color }}; }
        html, body { margin: 0; padding: 0; font-family: -apple-system, "Segoe UI", system-ui, sans-serif; }
        .wrap {
            padding: 1.5rem; background: #fff;
            border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,.06);
        }
        h2 { margin: 0 0 1rem; color: #111827; font-size: 1.2rem; }
        ul { list-style: none; padding: 0; margin: 0;
             display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: .5rem; }
        li { display: flex; align-items: center; gap: .6rem; padding: .6rem .8rem;
             background: #f9fafb; border-radius: 8px; font-size: .95rem; color: #111827; }
        .dot { width: 10px; height: 10px; border-radius: 50%; flex: 0 0 auto; }
        @media (prefers-color-scheme: dark) {
            .wrap { background: #1e293b; }
            h2 { color: #f3f4f6; }
            li { background: #0f172a; color: #f3f4f6; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <h2>{{ __('embed.instructors.heading') }}</h2>
        @if (! empty($instructors))
            <ul>
                @foreach ($instructors as $i)
                    <li><span class="dot" style="background: {{ $i['color'] }}"></span>{{ $i['name'] }}</li>
                @endforeach
            </ul>
        @else
            <p style="color: #6b7280;">{{ __('embed.instructors.empty') }}</p>
        @endif
    </div>
</body>
</html>
