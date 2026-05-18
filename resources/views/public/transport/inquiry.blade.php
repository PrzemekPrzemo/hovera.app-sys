<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('public/transport_inquiry.title') }} — hovera</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <style>
        :root { --primary: #A8956B; --bg: #F7F4EF; --text: #1F1A17; --muted: #6b7280; --border: #d4cdb8; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--text); }
        body { padding: 2rem 1rem; }
        .container { max-width: 640px; margin: 0 auto; }
        .logo { text-align: center; margin-bottom: 1.5rem; font-size: 1.4rem; font-weight: 700; color: #3D2E22; }
        .card { background: #fff; border-radius: 16px; padding: 2rem; box-shadow: 0 6px 24px rgba(0,0,0,.06); }
        h1 { margin: 0 0 .35rem; font-size: 1.5rem; color: #3D2E22; }
        .subtitle { color: var(--muted); margin-bottom: 1.5rem; font-size: .92rem; line-height: 1.5; }
        .form-row { display: flex; flex-direction: column; gap: .35rem; margin-bottom: 1rem; }
        .row-two { display: grid; gap: 1rem; grid-template-columns: 1fr 1fr; }
        label { font-weight: 600; font-size: .88rem; color: #3D2E22; }
        .helper { font-size: .78rem; color: var(--muted); }
        input[type=text], input[type=email], input[type=tel], input[type=number], input[type=date], input[type=time], textarea { padding: .65rem .85rem; border: 1px solid var(--border); border-radius: 8px; font: inherit; background: #fff; color: var(--text); width: 100%; }
        textarea { min-height: 80px; resize: vertical; }
        input:focus, textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 25%, transparent); }
        .checkbox { display: flex; gap: .5rem; align-items: flex-start; margin: 1rem 0; font-size: .85rem; }
        button[type=submit] { width: 100%; padding: .9rem 1.2rem; background: var(--primary); color: #fff; border: 0; border-radius: 8px; font-weight: 700; font-size: 1rem; cursor: pointer; }
        button[type=submit]:hover { filter: brightness(0.95); }
        .errors { background: #fef2f2; color: #991b1b; padding: .75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: .88rem; }
        .errors ul { margin: .25rem 0 0; padding-left: 1.25rem; }
        .direct-banner { background: color-mix(in srgb, var(--primary) 12%, #fff); border: 1px solid color-mix(in srgb, var(--primary) 35%, var(--border)); color: #3D2E22; padding: .75rem 1rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: .9rem; line-height: 1.5; }
        .direct-banner a { color: var(--primary); text-decoration: underline; }
        .direct-banner strong { color: #3D2E22; }
        @media (prefers-color-scheme: dark) {
            html, body { background: #1F1A17; color: #F7F4EF; }
            .card { background: #2a221c; }
            .logo, h1, label { color: #E9E2D3; }
            .subtitle, .helper { color: #C8B8A4; }
            input, textarea { background: #1F1A17; border-color: #4a3d31; color: #F7F4EF; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">hovera</div>

        <div class="card">
            <h1>{{ __('public/transport_inquiry.heading') }}</h1>
            <div class="subtitle">{{ __('public/transport_inquiry.subtitle') }}</div>

            @if (! empty($targetTransporter))
                <div class="direct-banner">
                    {!! __('public/transport_inquiry.direct_target_banner', ['name' => '<strong>'.e($targetTransporter->name).'</strong>']) !!}
                    <br>
                    <a href="{{ route('public.transport.inquiry') }}">{{ __('public/transport_inquiry.direct_target_switch_to_broadcast') }}</a>
                </div>
            @endif

            @if ($errors->any())
                <div class="errors">
                    <strong>{{ __('public/transport_inquiry.errors_heading') }}</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="post" action="{{ route('public.transport.inquiry.submit') }}">
                @csrf
                @if (! empty($targetTransporter))
                    <input type="hidden" name="transporter" value="{{ $targetTransporter->slug }}">
                @endif

                <div class="row-two">
                    <div class="form-row">
                        <label for="customer_name">{{ __('public/transport_inquiry.label.customer_name') }}</label>
                        <input type="text" name="customer_name" id="customer_name" required maxlength="120" value="{{ $old['customer_name'] }}">
                    </div>
                    <div class="form-row">
                        <label for="customer_email">{{ __('public/transport_inquiry.label.customer_email') }}</label>
                        <input type="email" name="customer_email" id="customer_email" required maxlength="255" value="{{ $old['customer_email'] }}">
                    </div>
                </div>

                <div class="form-row">
                    <label for="customer_phone">{{ __('public/transport_inquiry.label.customer_phone') }}</label>
                    <input type="tel" name="customer_phone" id="customer_phone" maxlength="40" value="{{ $old['customer_phone'] }}">
                </div>

                <div class="form-row">
                    <label for="pickup_address">{{ __('public/transport_inquiry.label.pickup_address') }}</label>
                    <input type="text" name="pickup_address" id="pickup_address" required maxlength="255" value="{{ $old['pickup_address'] }}" placeholder="{{ __('public/transport_inquiry.placeholder.pickup_address') }}">
                </div>

                <div class="form-row">
                    <label for="dropoff_address">{{ __('public/transport_inquiry.label.dropoff_address') }}</label>
                    <input type="text" name="dropoff_address" id="dropoff_address" required maxlength="255" value="{{ $old['dropoff_address'] }}" placeholder="{{ __('public/transport_inquiry.placeholder.dropoff_address') }}">
                </div>

                <div class="row-two">
                    <div class="form-row">
                        <label for="preferred_date">{{ __('public/transport_inquiry.label.preferred_date') }}</label>
                        <input type="date" name="preferred_date" id="preferred_date" required value="{{ $old['preferred_date'] }}" min="{{ now()->toDateString() }}">
                    </div>
                    <div class="form-row">
                        <label for="preferred_time">{{ __('public/transport_inquiry.label.preferred_time') }}</label>
                        <input type="time" name="preferred_time" id="preferred_time" value="{{ $old['preferred_time'] }}">
                    </div>
                </div>

                <label class="checkbox">
                    <input type="checkbox" name="flexible_date" value="1">
                    <span>{{ __('public/transport_inquiry.label.flexible_date') }}</span>
                </label>

                <div class="form-row">
                    <label for="horse_count">{{ __('public/transport_inquiry.label.horse_count') }}</label>
                    <input type="number" name="horse_count" id="horse_count" required min="1" max="15" value="{{ $old['horse_count'] }}">
                </div>

                <div class="form-row">
                    <label for="notes">{{ __('public/transport_inquiry.label.notes') }}</label>
                    <textarea name="notes" id="notes" maxlength="2000" placeholder="{{ __('public/transport_inquiry.placeholder.notes') }}">{{ $old['notes'] }}</textarea>
                </div>

                <label class="checkbox">
                    <input type="checkbox" name="terms" required>
                    <span>{!! __('public/transport_inquiry.label.terms') !!}</span>
                </label>

                <button type="submit">{{ __('public/transport_inquiry.action.submit') }}</button>

                {{-- Disclaimer: Hovera = pośrednik marketplace, nie przewoźnik.
                     Wymagany legal compliance — informuje użytkownika ZANIM wyśle
                     zapytanie, że umowa będzie z wybranym przewoźnikiem (nie z Hovera). --}}
                <p style="margin-top:1rem;font-size:.78rem;color:var(--muted);font-style:italic;line-height:1.5;">
                    {!! __('public/transport_inquiry.disclaimer_intermediary') !!}
                </p>
            </form>
        </div>
    </div>
</body>
</html>
