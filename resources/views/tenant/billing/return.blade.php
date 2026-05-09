<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('billing.return.title') }} — {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #F7F4EF; color: #3D2E22; margin: 0; }
        .box { max-width: 540px; margin: 4rem auto; padding: 2rem; background: white; border: 1px solid #E9E2D3; border-radius: 12px; text-align: center; }
        h1 { margin: 0 0 .5rem; }
        .icon { font-size: 3rem; }
        a.primary { display: inline-block; margin-top: 1.25rem; background: #A8956B; color: white; padding: .75rem 1.5rem; border-radius: 8px; font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>
<div class="box">
    @if ($isActive)
        <div class="icon">✅</div>
        <h1>{{ __('billing.return.success_title') }}</h1>
        <p>{{ __('billing.return.success_body') }}</p>
        <a href="/app" class="primary">{{ __('billing.return.go_to_app') }}</a>
    @else
        <div class="icon">⏳</div>
        <h1>{{ __('billing.return.pending_title') }}</h1>
        <p>{{ __('billing.return.pending_body') }}</p>
        <a href="{{ route('billing.show') }}" class="primary">{{ __('billing.return.refresh') }}</a>
    @endif
</div>
</body>
</html>
