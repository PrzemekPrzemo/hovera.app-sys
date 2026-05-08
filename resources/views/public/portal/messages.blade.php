<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('portal/messages.title', ['tenant' => $tenant->name]) }}</title>
    <meta name="robots" content="noindex">
    <style>
        :root { --primary: {{ $primary_color }}; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: #fafafa; color: #1f2937; }
        body { padding: 1rem; }
        .container { max-width: 720px; margin: 0 auto; }
        .card { background: #fff; border-radius: 14px; padding: 1.25rem; box-shadow: 0 4px 18px rgba(0,0,0,.05); margin-bottom: 1rem; }
        .back { display: inline-block; margin-bottom: .8rem; color: #6b7280; text-decoration: none; font-size: .9rem; }
        h1 { margin: 0 0 .25rem; font-size: 1.4rem; color: var(--primary); }
        .subtitle { color: #6b7280; margin-bottom: 1rem; font-size: .9rem; }
        .empty { color: #9ca3af; font-style: italic; padding: .8rem 0; }
        .message { padding: .9rem 0; border-bottom: 1px solid #f3f4f6; }
        .message:last-of-type { border-bottom: 0; }
        .message-head { display: flex; justify-content: space-between; gap: .5rem; align-items: baseline; margin-bottom: .25rem; }
        .message-head strong { font-size: .95rem; }
        .message-head .date { color: #6b7280; font-size: .85rem; white-space: nowrap; }
        .pill { display: inline-block; padding: .15rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; margin-right: .35rem; background: #e5e7eb; color: #374151; }
        .pill.primary { background: color-mix(in srgb, var(--primary) 18%, white); color: var(--primary); }
        .meta { color: #6b7280; font-size: .85rem; margin-top: .15rem; }
        .pagination { margin-top: 1rem; }
        .pagination a, .pagination span { display: inline-block; padding: .35rem .7rem; border-radius: 6px; margin-right: .25rem; font-size: .85rem; text-decoration: none; color: #374151; border: 1px solid #e5e7eb; }
        .pagination .active { background: var(--primary); color: #fff; border-color: var(--primary); }
        @media (prefers-color-scheme: dark) {
            body { background: #0f172a; color: #e5e7eb; }
            .card { background: #1e293b; }
            .back { color: #94a3b8; }
            .subtitle { color: #94a3b8; }
            .message { border-color: #334155; }
            .message-head .date, .meta { color: #94a3b8; }
            .pagination a, .pagination span { background: #0f172a; border-color: #334155; color: #cbd5e1; }
        }
    </style>
</head>
<body>
    <div class="container">
        <a class="back" href="{{ route('client_portal.dashboard', ['slug' => $tenant->slug]) }}">{{ __('portal/messages.back') }}</a>

        <div class="card">
            <h1>{{ __('portal/messages.heading') }}</h1>
            <div class="subtitle">{{ $tenant->name }}</div>

            @forelse ($messages as $message)
                <div class="message">
                    <div class="message-head">
                        <div>
                            <span class="pill primary">{{ $message->label() }}</span>
                            <strong>{{ $message->subject }}</strong>
                        </div>
                        <div class="date">{{ $message->sent_at->format('d.m.Y H:i') }}</div>
                    </div>
                    <div class="meta">→ {{ $message->to_email }}</div>
                </div>
            @empty
                <div class="empty">{{ __('portal/messages.empty') }}</div>
            @endforelse

            @if ($messages->hasPages())
                <div class="pagination">{{ $messages->links() }}</div>
            @endif
        </div>
    </div>
</body>
</html>
