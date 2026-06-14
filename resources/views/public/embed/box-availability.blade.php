@php /** @var \App\Models\Central\Tenant $tenant */ @endphp
<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('embed.box_availability.title', ['tenant' => $tenant->name]) }}</title>
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
        .wrap {
            display: flex; align-items: center; gap: 1rem;
            padding: 1.5rem; background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px; box-shadow: 0 4px 18px rgba(168, 149, 107, 0.08);
        }
        .pill {
            display: grid; place-items: center;
            min-width: 88px; min-height: 88px;
            border-radius: 999px;
            font-weight: 800; font-size: 2.1rem;
            color: var(--brown);
            background: var(--bg);
            border: 3px solid var(--ochre);
        }
        .pill.full {
            color: var(--brown-soft);
            border-color: var(--line);
            background: #fafaf7;
        }
        .body strong { display: block; font-size: 1.1rem; color: var(--brown); margin-bottom: .25rem; font-weight: 700; }
        .body span { display: block; color: var(--brown-soft); font-size: .9rem; line-height: 1.4; }
        .cta {
            display: inline-block; margin-top: .65rem;
            padding: .55rem 1.1rem;
            background: var(--ochre); color: #fff;
            text-decoration: none; border-radius: 8px;
            font-weight: 600; font-size: .9rem;
            transition: background .15s ease;
        }
        .cta:hover { background: var(--ochre-dark); }
    </style>
    <x-google-analytics />
</head>
<body>
    <div class="wrap">
        @php
            $publicPrefix = config('hovera.public_site.prefix', 's');
            $inquiryUrl = url('/'.$publicPrefix.'/'.$tenant->slug.'/box-inquiry?source=embed');
        @endphp
        @if ($box_availability && $box_availability['free'] > 0)
            @php
                $count = (int) $box_availability['free'];
                $key = $count === 1
                    ? 'embed.box_availability.free_singular'
                    : ($count < 5 ? 'embed.box_availability.free_few' : 'embed.box_availability.free_many');
            @endphp
            <div class="pill">{{ $count }}</div>
            <div class="body">
                <strong>{{ __($key, ['count' => $count]) }}</strong>
                <span>{{ __('embed.box_availability.free_subtitle', ['tenant' => $tenant->name]) }}</span>
                <a class="cta" href="{{ $inquiryUrl }}" target="_blank" rel="noopener">{{ __('embed.box_availability.cta_inquire') }}</a>
            </div>
        @elseif ($box_availability)
            <div class="pill full">0</div>
            <div class="body">
                <strong>{{ __('embed.box_availability.full_heading') }}</strong>
                <span>{{ __('embed.box_availability.full_subtitle', ['tenant' => $tenant->name]) }}</span>
                <a class="cta" href="{{ $inquiryUrl }}" target="_blank" rel="noopener">{{ __('embed.box_availability.cta_inquire_waitlist') }}</a>
            </div>
        @else
            <div class="body">
                <strong>{{ $tenant->name }}</strong>
                <a class="cta" href="{{ url('/'.$publicPrefix.'/'.$tenant->slug) }}" target="_blank" rel="noopener">{{ __('embed.box_availability.cta_open') }}</a>
            </div>
        @endif
    </div>
</body>
</html>
