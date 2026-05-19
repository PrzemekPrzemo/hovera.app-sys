<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('public/transport_lead_portal.my_inquiries.title') }} — hovera</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <style>
        :root { --primary: #A8956B; --primary-dark: #8F8576; --bg: #F7F4EF; --text: #1F1A17; --success: #166534; --muted: #6b7280; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, system-ui, sans-serif; background: var(--bg); color: var(--text); color-scheme: light; }
        body { padding: 2rem 1rem; }
        .container { max-width: 960px; margin: 0 auto; }
        .header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; }
        .logo { font-size: 1.3rem; font-weight: 700; color: #3D2E22; }
        .logout { padding: .4rem .8rem; background: transparent; border: 1px solid #d4cdb8; border-radius: 8px; color: var(--text); cursor: pointer; font-size: .82rem; }
        .card { background: #fff; border-radius: 16px; padding: 1.75rem 1.5rem; box-shadow: 0 6px 24px rgba(0,0,0,.06); margin-bottom: 1rem; }
        h1 { margin: 0 0 1rem; color: #3D2E22; }
        .status-banner { background: #ecfdf5; color: var(--success); padding: .85rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: .9rem; border-left: 4px solid var(--success); }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        th { text-align: left; padding: .65rem .5rem; background: var(--bg); color: #3D2E22; font-size: .78rem; text-transform: uppercase; letter-spacing: .05em; }
        td { padding: .7rem .5rem; border-bottom: 1px solid #f0e8d4; vertical-align: top; }
        .badge { display: inline-block; padding: .15rem .55rem; border-radius: 12px; background: #ecfdf5; color: var(--success); font-size: .75rem; font-weight: 600; }
        .badge-open { background: #fef9e7; color: #5d4d22; }
        .badge-accepted { background: #ecfdf5; color: var(--success); }
        .badge-cancelled, .badge-expired { background: #f3f4f6; color: var(--muted); }
        a.portal-link { color: var(--primary-dark); text-decoration: none; font-weight: 600; }
        a.portal-link:hover { text-decoration: underline; }
        .empty { text-align: center; padding: 3rem 1rem; color: var(--muted); }
        .empty .cta { display: inline-block; margin-top: 1rem; padding: .8rem 1.4rem; background: var(--primary); color: #fff; border-radius: 8px; text-decoration: none; font-weight: 700; }
        .route { line-height: 1.4; }
        .route .from { color: #3D2E22; font-weight: 500; }
        .route .arrow { color: var(--muted); margin: 0 .25rem; }
    </style>
</head>
<body>
<div class="container">
    <div class="header-bar">
        <div class="logo">hovera · transport</div>
        <form method="POST" action="{{ url('/app/logout') }}" style="margin:0;">
            @csrf
            <button type="submit" class="logout">{{ __('public/transport_lead_portal.my_inquiries.logout') }}</button>
        </form>
    </div>

    @if (session('status'))
        <div class="status-banner">{{ session('status') }}</div>
    @endif

    <div class="card">
        <h1>{{ __('public/transport_lead_portal.my_inquiries.heading') }}</h1>

        @if ($leads->isEmpty())
            <div class="empty">
                {{ __('public/transport_lead_portal.my_inquiries.empty') }}
                <br>
                <a href="{{ url('/transport/zapytanie') }}" class="cta">{{ __('public/transport_lead_portal.my_inquiries.empty_cta') }}</a>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ __('public/transport_lead_portal.my_inquiries.column.date') }}</th>
                        <th>{{ __('public/transport_lead_portal.my_inquiries.column.route') }}</th>
                        <th>{{ __('public/transport_lead_portal.my_inquiries.column.preferred_date') }}</th>
                        <th>{{ __('public/transport_lead_portal.my_inquiries.column.horses') }}</th>
                        <th>{{ __('public/transport_lead_portal.my_inquiries.column.status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($leads as $lead)
                        <tr>
                            <td>{{ optional($lead->created_at)->format('Y-m-d') ?: '—' }}</td>
                            <td>
                                <div class="route">
                                    <span class="from">{{ \Illuminate\Support\Str::limit($lead->pickup_address, 40) }}</span>
                                    <span class="arrow">→</span>
                                    <span class="from">{{ \Illuminate\Support\Str::limit($lead->dropoff_address, 40) }}</span>
                                </div>
                            </td>
                            <td>{{ optional($lead->preferred_date)->toDateString() ?: '—' }}</td>
                            <td>{{ $lead->horse_count }}</td>
                            <td>
                                <span class="badge badge-{{ $lead->status }}">{{ __('public/transport_lead_portal.status.'.$lead->status) }}</span>
                            </td>
                            <td>
                                @if ($lead->access_slug)
                                    <a class="portal-link" href="{{ route('public.transport.lead_portal', ['slug' => $lead->access_slug]) }}">{{ __('public/transport_lead_portal.my_inquiries.view_link') }}</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
</body>
</html>
