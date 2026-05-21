@php /** @var \App\Models\Central\Tenant $tenant */ @endphp
<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('embed.pricing.title', ['tenant' => $tenant->name]) }}</title>
    <style>
        :root { --primary: {{ $primary_color }}; }
        html, body { margin: 0; padding: 0; font-family: -apple-system, "Segoe UI", system-ui, sans-serif; }
        .wrap {
            padding: 1.5rem; background: #fff;
            border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,.06);
        }
        h2 { margin: 0 0 1rem; color: #111827; font-size: 1.2rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: .65rem .25rem; border-bottom: 1px solid #e5e7eb; font-size: .95rem; }
        th { font-weight: 500; color: #6b7280; font-size: .8rem; text-transform: uppercase; letter-spacing: .03em; }
        td.price { text-align: right; font-weight: 600; color: var(--primary); white-space: nowrap; }
        td.unit { text-align: right; color: #6b7280; font-size: .85rem; white-space: nowrap; }
        .note { margin-top: 1rem; color: #9ca3af; font-size: .8rem; }
        @media (prefers-color-scheme: dark) {
            .wrap { background: #1e293b; }
            h2 { color: #f3f4f6; }
            th, td { border-color: #334155; color: #e5e7eb; }
            th { color: #94a3b8; }
            td.unit { color: #94a3b8; }
        }
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
            <p style="color: #6b7280;">{{ __('embed.pricing.empty') }}</p>
        @endif
    </div>
</body>
</html>
