<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('public/transport_review.form.title', ['transporter' => $transporter?->name ?? '—']) }}</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <style>
        :root { --primary: #A8956B; --bg: #F7F4EF; --text: #1F1A17; --muted: #6b7280; --border: #d4cdb8; --card: #fff; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--text); }
        .wrap { max-width: 600px; margin: 0 auto; padding: 2.5rem 1.25rem; }
        .card { background: var(--card); border-radius: 14px; padding: 1.75rem; box-shadow: 0 3px 14px rgba(0,0,0,.06); }
        h1 { margin: 0 0 .5rem; font-size: 1.4rem; color: #3D2E22; }
        .lead { color: var(--muted); margin-bottom: 1.5rem; font-size: .98rem; }
        .stars { display: flex; gap: .25rem; flex-direction: row-reverse; justify-content: flex-end; margin-bottom: 1.25rem; }
        .stars input { display: none; }
        .stars label { font-size: 2.25rem; color: #d4d4d4; cursor: pointer; line-height: 1; transition: color .15s; }
        .stars label:hover, .stars label:hover ~ label,
        .stars input:checked ~ label { color: var(--primary); }
        textarea { width: 100%; min-height: 140px; padding: .75rem; border-radius: 10px; border: 1px solid var(--border); font-family: inherit; font-size: 1rem; background: #fff; color: var(--text); resize: vertical; }
        textarea:focus { outline: 2px solid var(--primary); outline-offset: 2px; }
        .meta { color: var(--muted); font-size: .85rem; margin-top: .5rem; }
        .submit { display: inline-block; margin-top: 1.5rem; padding: .85rem 1.6rem; background: var(--primary); color: #fff; border: 0; border-radius: 10px; font-weight: 700; font-size: 1rem; cursor: pointer; }
        .submit:hover { transform: translateY(-1px); }
        .errors { background: #fee; border: 1px solid #fcc; border-radius: 8px; padding: .75rem 1rem; margin-bottom: 1rem; color: #800; }
        .disclaimer { font-size: .8rem; color: var(--muted); margin-top: 1.5rem; line-height: 1.4; }
        .disclaimer a { color: var(--primary); }
                /* Mobile (≤600px) — kompaktowy padding karty, większe gwiazdki dla
           kciuka, button full-width żeby był łatwy do tapnięcia. */
        @media (max-width: 600px) {
            .wrap { padding: 1.5rem .75rem; }
            .card { padding: 1.25rem 1rem; }
            h1 { font-size: 1.2rem; }
            .lead { font-size: .92rem; }
            .stars label { font-size: 2.6rem; padding: .15rem .1rem; /* większe taps */ }
            textarea { font-size: 16px; /* anty-zoom iOS */ }
            .submit { display: block; width: 100%; padding: 1rem; font-size: 1.05rem; }
        }
            /* Light mode only — wymog user spec. Brak prefers-color-scheme:dark override. */
        html { color-scheme: light; }
    </style>
    <x-google-analytics />
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>{{ __('public/transport_review.form.heading') }}</h1>
            <p class="lead">
                {{ __('public/transport_review.form.lead', ['transporter' => $transporter?->name ?? '—']) }}
            </p>

            @if ($errors->any())
                <div class="errors">
                    <ul style="margin:0;padding-left:1.1rem;">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('public.transport.review.submit', ['token' => $token]) }}">
                @csrf

                <label style="display:block;font-weight:600;margin-bottom:.5rem;">
                    {{ __('public/transport_review.form.rating_label') }}
                </label>
                <div class="stars" role="radiogroup" aria-label="{{ __('public/transport_review.form.rating_label') }}">
                    @for ($i = 5; $i >= 1; $i--)
                        <input type="radio" name="rating" id="r{{ $i }}" value="{{ $i }}" {{ old('rating') == $i ? 'checked' : '' }}>
                        <label for="r{{ $i }}" title="{{ $i }} / 5">★</label>
                    @endfor
                </div>

                <label for="comment" style="display:block;font-weight:600;margin-bottom:.5rem;">
                    {{ __('public/transport_review.form.comment_label') }}
                </label>
                <textarea id="comment" name="comment" maxlength="2000" placeholder="{{ __('public/transport_review.form.comment_placeholder') }}">{{ old('comment') }}</textarea>
                <div class="meta">{{ __('public/transport_review.form.comment_hint') }}</div>

                <button type="submit" class="submit">
                    {{ __('public/transport_review.form.submit') }}
                </button>
            </form>

            <p class="disclaimer">
                {!! __('public/transport_review.form.disclaimer_intermediary') !!}
            </p>
        </div>
    </div>
</body>
</html>
