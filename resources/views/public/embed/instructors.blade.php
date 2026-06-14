@php /** @var \App\Models\Central\Tenant $tenant */ @endphp
<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('embed.instructors.title', ['tenant' => $tenant->name]) }}</title>
    <style>
        :root {
            --ochre: #A8956B;
            --brown: #3D2E22;
            --brown-soft: #6b5b4a;
            --bg: #F7F4EF;
            --line: #E9E2D3;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: transparent; font-family: -apple-system, "Segoe UI", system-ui, sans-serif; color: var(--brown); }
        .wrap {
            padding: 1.5rem; background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px; box-shadow: 0 4px 18px rgba(168, 149, 107, 0.08);
        }
        h2 { margin: 0 0 1rem; color: var(--brown); font-size: 1.2rem; font-weight: 700; }
        ul { list-style: none; padding: 0; margin: 0;
             display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: .5rem; }
        li { display: flex; align-items: center; gap: .65rem; padding: .65rem .85rem;
             background: var(--bg); border: 1px solid var(--line);
             border-radius: 8px; font-size: .95rem; color: var(--brown); font-weight: 500; }
        .dot { width: 10px; height: 10px; border-radius: 50%; flex: 0 0 auto; background: var(--ochre); }
        .empty { color: var(--brown-soft); }
    </style>
    <x-google-analytics />
</head>
<body>
    <div class="wrap">
        <h2>{{ __('embed.instructors.heading') }}</h2>
        @if (! empty($instructors))
            <ul>
                @foreach ($instructors as $i)
                    <li><span class="dot" @if (! empty($i['color'])) style="background: {{ $i['color'] }}" @endif></span>{{ $i['name'] }}</li>
                @endforeach
            </ul>
        @else
            <p class="empty">{{ __('embed.instructors.empty') }}</p>
        @endif
    </div>
</body>
</html>
