<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $horse->name }} — {{ $tenant->name }}</title>
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
        dl { display: grid; grid-template-columns: max-content 1fr; gap: .35rem 1rem; padding: .8rem 1rem; background: #f9fafb; border-radius: 8px; font-size: .9rem; }
        dt { color: #6b7280; }
        dd { margin: 0; font-weight: 500; }
        h2 { font-size: 1.05rem; color: var(--primary); margin: 0 0 .8rem; }
        .empty { color: #9ca3af; font-style: italic; padding: .5rem 0; }
        .record { padding: .9rem 0; border-bottom: 1px solid #f3f4f6; }
        .record:last-of-type { border-bottom: 0; }
        .record .head { display: flex; justify-content: space-between; gap: .5rem; align-items: baseline; margin-bottom: .25rem; }
        .record .head strong { font-size: .95rem; }
        .record .head .date { color: #6b7280; font-size: .85rem; white-space: nowrap; }
        .record .summary { color: #374151; font-size: .9rem; line-height: 1.4; }
        .record .meta { color: #9ca3af; font-size: .8rem; margin-top: .35rem; }
        .pill { display: inline-block; padding: .15rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; margin-right: .35rem; }
        .pill.danger { background: #fee2e2; color: #991b1b; }
        .pill.warning { background: #fef3c7; color: #92400e; }
        .pill.primary { background: color-mix(in srgb, var(--primary) 18%, white); color: var(--primary); }
        .pill.gray { background: #e5e7eb; color: #374151; }
        @media (prefers-color-scheme: dark) {
            body { background: #0f172a; color: #e5e7eb; }
            .card { background: #1e293b; }
            .back { color: #94a3b8; }
            .subtitle { color: #94a3b8; }
            dl { background: #0f172a; }
            dt { color: #94a3b8; }
            .record { border-color: #334155; }
            .record .summary { color: #cbd5e1; }
            .record .meta, .record .head .date { color: #64748b; }
        }
    </style>
</head>
<body>
    <div class="container">
        <a class="back" href="{{ route('client_portal.dashboard', ['slug' => $tenant->slug]) }}">← Wróć do panelu</a>

        <div class="card">
            <h1>{{ $horse->name }}</h1>
            <div class="subtitle">{{ $tenant->name }}</div>

            <dl>
                @if ($horse->breed)<dt>Rasa</dt><dd>{{ $horse->breed }}</dd>@endif
                @if ($horse->sex)<dt>Płeć</dt><dd>{{ $horse->sex }}</dd>@endif
                @if ($horse->color)<dt>Maść</dt><dd>{{ $horse->color }}</dd>@endif
                @if ($horse->birth_date)
                    <dt>Wiek</dt><dd>{{ (int) $horse->birth_date->diffInYears(now()) }} lat ({{ $horse->birth_date->format('Y') }})</dd>
                @endif
                @if ($horse->microchip)<dt>Mikroczip</dt><dd>{{ $horse->microchip }}</dd>@endif
                @if ($horse->passport_number)<dt>Paszport</dt><dd>{{ $horse->passport_number }}</dd>@endif
            </dl>
        </div>

        <div class="card">
            <h2>Historia weterynaryjna</h2>
            @forelse ($records as $record)
                @php
                    $isOverdue = $record->next_due_at && $record->next_due_at->isPast();
                    $isSoon = $record->next_due_at && ! $isOverdue && $record->next_due_at->lte(now()->addDays(30));
                @endphp
                <div class="record">
                    <div class="head">
                        <div>
                            <span class="pill primary">{{ $record->type->label() }}</span>
                            <strong>{{ $record->summary }}</strong>
                        </div>
                        <div class="date">{{ $record->performed_at->format('d.m.Y') }}</div>
                    </div>
                    @if ($record->details)
                        <div class="summary">{{ $record->details }}</div>
                    @endif
                    <div class="meta">
                        @if ($record->performed_by) Wykonał: {{ $record->performed_by }} @endif
                        @if ($record->next_due_at)
                            · Następny zabieg: {{ $record->next_due_at->format('d.m.Y') }}
                            @if ($isOverdue)
                                <span class="pill danger">Przeterminowane</span>
                            @elseif ($isSoon)
                                <span class="pill warning">Wkrótce</span>
                            @endif
                        @endif
                    </div>
                </div>
            @empty
                <div class="empty">Brak wpisów weterynaryjnych.</div>
            @endforelse
        </div>
    </div>
</body>
</html>
