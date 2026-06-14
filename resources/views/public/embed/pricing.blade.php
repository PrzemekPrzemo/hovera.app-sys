@php /** @var \App\Models\Central\Tenant $tenant */ @endphp
<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('embed.pricing.title', ['tenant' => $tenant->name]) }}</title>
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
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: .7rem .25rem; border-bottom: 1px solid var(--line); font-size: .95rem; color: var(--brown); }
        th { font-weight: 600; color: var(--brown-soft); font-size: .78rem; text-transform: uppercase; letter-spacing: .04em; }
        td.price { text-align: right; font-weight: 700; color: var(--ochre); white-space: nowrap; }
        td.unit { text-align: right; color: var(--brown-soft); font-size: .85rem; white-space: nowrap; }
        .note { margin-top: 1rem; color: var(--brown-soft); font-size: .8rem; }
        .empty { color: var(--brown-soft); }
    </style>
    <x-google-analytics />
</head>
<body>
    <div class="wrap">
        <h2>{{ __('embed.pricing.heading') }}</h2>
        @if (! empty($pricing))
            <table>
                <thead>
                    <tr><th>{{ __('embed.pricing.col_item') }}</th><th></th><th>{{ __('embed.pricing.col_price') }}</th></tr>
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
            <p class="note">{{ __('embed.pricing.note') }}</p>
        @else
            <p class="empty">{{ __('embed.pricing.empty') }}</p>
        @endif
    </div>
</body>
</html>
