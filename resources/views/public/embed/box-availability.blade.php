@php /** @var \App\Models\Central\Tenant $tenant */ @endphp
<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('embed.box_availability.title', ['tenant' => $tenant->name]) }}</title>
    <style>
        :root { --primary: {{ $primary_color }}; }
        html, body { margin: 0; padding: 0; font-family: -apple-system, "Segoe UI", system-ui, sans-serif; }
        .wrap {
            display: flex; align-items: center; gap: 1rem;
            padding: 1.5rem; background: #fff;
            border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,.06);
        }
        .pill {
            display: grid; place-items: center;
            min-width: 84px; min-height: 84px;
            border-radius: 999px;
            font-weight: 800; font-size: 2rem;
            color: #fff;
        }
        .pill.free { background: var(--primary); }
        .pill.full { background: #6b7280; }
        .body strong { display: block; font-size: 1.1rem; color: #111827; margin-bottom: .25rem; }
        .body span { display: block; color: #6b7280; font-size: .9rem; }
        .cta {
            display: inline-block; margin-top: .5rem;
            padding: .5rem 1rem; background: var(--primary); color: #fff;
            text-decoration: none; border-radius: 8px; font-weight: 600; font-size: .9rem;
        }
        @media (prefers-color-scheme: dark) {
            .wrap { background: #1e293b; }
            .body strong { color: #f3f4f6; }
            .body span { color: #94a3b8; }
        }
    </style>
    <x-google-analytics />
</head>
<body>
    <div class="wrap">
        @if ($box_availability && $box_availability['free'] > 0)
            @php
                // Polish has 3 plural forms (1 / 2-4 / 5+); EN degrades to singular/many.
                $count = (int) $box_availability['free'];
                $key = $count === 1
                    ? 'embed.box_availability.free_singular'
                    : ($count < 5 ? 'embed.box_availability.free_few' : 'embed.box_availability.free_many');
            @endphp
            @php
                $publicPrefix = config('hovera.public_site.prefix', 's');
                $inquiryUrl = url('/'.$publicPrefix.'/'.$tenant->slug.'/box-inquiry?source=embed');
            @endphp
            <div class="pill free">{{ $count }}</div>
            <div class="body">
                <strong>{{ __($key, ['count' => $count]) }}</strong>
                <span>{{ __('embed.box_availability.free_subtitle', ['tenant' => $tenant->name]) }}</span>
                <a class="cta" href="{{ $inquiryUrl }}" target="_blank" rel="noopener">{{ __('embed.box_availability.cta_inquire') }}</a>
            </div>
        @elseif ($box_availability)
            @php
                $publicPrefix = config('hovera.public_site.prefix', 's');
                $inquiryUrl = url('/'.$publicPrefix.'/'.$tenant->slug.'/box-inquiry?source=embed');
            @endphp
            <div class="pill full">0</div>
            <div class="body">
                <strong>{{ __('embed.box_availability.full_heading') }}</strong>
                <span>{{ __('embed.box_availability.full_subtitle', ['tenant' => $tenant->name]) }}</span>
                <a class="cta" href="{{ $inquiryUrl }}" target="_blank" rel="noopener">{{ __('embed.box_availability.cta_inquire_waitlist') }}</a>
            </div>
        @else
            <div class="body">
                <strong>{{ $tenant->name }}</strong>
                <a class="cta" href="{{ url('/'.config('hovera.public_site.prefix', 's').'/'.$tenant->slug) }}" target="_blank" rel="noopener">{{ __('embed.box_availability.cta_open') }}</a>
            </div>
        @endif
    </div>
</body>
</html>
