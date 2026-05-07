<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Moje rezerwacje — {{ $tenant->name }}</title>
    <meta name="robots" content="noindex">
    <style>
        :root { --primary: {{ $primary_color }}; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: #fafafa; color: #1f2937; }
        body { padding: 1rem; }
        .container { max-width: 720px; margin: 0 auto; }
        header.bar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1.25rem; background: #fff; border-radius: 14px; box-shadow: 0 4px 18px rgba(0,0,0,.05); margin-bottom: 1rem; }
        header.bar .who { font-weight: 600; }
        header.bar .who .stable { display: block; font-size: .8rem; color: #6b7280; font-weight: 400; }
        header.bar form { margin: 0; }
        header.bar button { padding: .5rem .9rem; background: transparent; border: 1px solid #e5e7eb; border-radius: 8px; color: #374151; font-size: .85rem; cursor: pointer; }
        header.bar button:hover { background: #f3f4f6; }
        .section { background: #fff; border-radius: 14px; padding: 1.25rem; box-shadow: 0 4px 18px rgba(0,0,0,.05); margin-bottom: 1rem; }
        .section h2 { margin: 0 0 .9rem; font-size: 1.05rem; color: var(--primary); }
        .empty { color: #9ca3af; font-style: italic; padding: .5rem 0; }
        .booking { display: grid; grid-template-columns: auto 1fr auto; gap: .75rem 1rem; padding: .8rem 0; border-bottom: 1px solid #f3f4f6; }
        .booking:last-child { border-bottom: 0; }
        .booking .when { font-weight: 600; }
        .booking .when .duration { display: block; font-size: .8rem; color: #6b7280; font-weight: 400; }
        .booking .what { font-size: .9rem; color: #4b5563; }
        .booking .what .meta { display: block; color: #9ca3af; font-size: .8rem; margin-top: .15rem; }
        .booking .actions a { display: inline-block; padding: .35rem .7rem; border: 1px solid #e5e7eb; border-radius: 6px; color: #b91c1c; font-size: .8rem; text-decoration: none; margin-left: .35rem; }
        .booking .actions a:hover { background: #fef2f2; }
        .booking .actions a.reschedule { color: var(--primary); }
        .booking .actions a.reschedule:hover { background: color-mix(in srgb, var(--primary) 8%, transparent); }
        .pass { padding: .8rem 0; border-bottom: 1px solid #f3f4f6; }
        .pass:last-of-type { border-bottom: 0; }
        .pass-head { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
        .pass-meta { color: #6b7280; font-size: .85rem; margin-top: .25rem; }
        .pass-bar { height: 6px; border-radius: 999px; background: #f3f4f6; margin-top: .5rem; overflow: hidden; }
        .pass-bar > span { display: block; height: 100%; background: var(--primary); transition: width .2s ease; }
        h3.muted { font-size: .85rem; color: #6b7280; margin: 1rem 0 .35rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
        .use { display: flex; justify-content: space-between; padding: .35rem 0; font-size: .85rem; }
        .muted { color: #6b7280; }
        .flash { background: #d1fae5; color: #065f46; padding: .65rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: .9rem; }
        .horse-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .8rem 0; border-bottom: 1px solid #f3f4f6; text-decoration: none; color: inherit; }
        .horse-row:last-of-type { border-bottom: 0; }
        .horse-row:hover { background: #f9fafb; margin: 0 -.5rem; padding: .8rem .5rem; border-radius: 8px; }
        .horse-row .meta { display: block; color: #9ca3af; font-size: .8rem; margin-top: .15rem; }
        .horse-alerts { display: flex; gap: .35rem; }
        .invoice-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .8rem 0; border-bottom: 1px solid #f3f4f6; text-decoration: none; color: inherit; }
        .invoice-row:last-of-type { border-bottom: 0; }
        .invoice-row:hover { background: #f9fafb; margin: 0 -.5rem; padding: .8rem .5rem; border-radius: 8px; }
        .invoice-row .meta { display: block; color: #9ca3af; font-size: .8rem; margin-top: .15rem; }
        .invoice-row .meta .overdue { color: #b91c1c; font-weight: 600; }
        .invoice-amount { font-weight: 700; color: var(--primary); white-space: nowrap; }
        .more { float: right; font-size: .75rem; font-weight: 500; color: var(--primary); text-decoration: none; }
        .more:hover { text-decoration: underline; }
        .message { padding: .65rem 0; border-bottom: 1px solid #f3f4f6; font-size: .9rem; }
        .message:last-of-type { border-bottom: 0; }
        .message-head { display: flex; justify-content: space-between; gap: .5rem; align-items: baseline; margin-bottom: .15rem; }
        .pill { display: inline-block; padding: .15rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
        .pill.req { background: #fef3c7; color: #92400e; }
        .pill.conf { background: #d1fae5; color: #065f46; }
        .pill.cancel { background: #fee2e2; color: #991b1b; }
        .pill.complete { background: #e5e7eb; color: #374151; }
        .pill.no { background: #fee2e2; color: #991b1b; }
        @media (max-width: 540px) {
            .booking { grid-template-columns: 1fr; }
            .booking .actions { text-align: right; }
        }
        @media (prefers-color-scheme: dark) {
            body { background: #0f172a; color: #e5e7eb; }
            header.bar, .section { background: #1e293b; }
            header.bar .who .stable { color: #94a3b8; }
            header.bar button { border-color: #334155; color: #e2e8f0; }
            header.bar button:hover { background: #0f172a; }
            .booking { border-color: #334155; }
            .booking .what { color: #cbd5e1; }
            .booking .what .meta { color: #64748b; }
            .booking .actions a { border-color: #334155; }
            .booking .actions a:hover { background: #1f2937; }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="bar">
            <div class="who">
                {{ $client->name }}
                <span class="stable">Panel klienta · {{ $tenant->name }}</span>
            </div>
            <form method="post" action="{{ route('client_portal.logout', ['slug' => $tenant->slug]) }}">
                @csrf
                <button type="submit">Wyloguj</button>
            </form>
        </header>

        @if (session('reschedule_success'))
            <div class="flash">✓ Rezerwacja przesunięta. Wysłaliśmy potwierdzenie mailem.</div>
        @endif

        <section class="section">
            <h2>Nadchodzące rezerwacje</h2>
            @forelse ($upcoming as $entry)
                <div class="booking">
                    <div class="when">
                        {{ $entry->starts_at->translatedFormat('d.m.Y · H:i') }}
                        <span class="duration">{{ (int) $entry->starts_at->diffInMinutes($entry->ends_at) }} min</span>
                    </div>
                    <div class="what">
                        @switch($entry->status->value)
                            @case('requested')
                                <span class="pill req">Oczekuje</span>
                                @break
                            @case('confirmed')
                                <span class="pill conf">Potwierdzona</span>
                                @break
                        @endswitch
                        <span class="meta">
                            {{ $entry->instructor?->name ? 'Instruktor: '.$entry->instructor->name : '' }}
                            @if ($entry->horse) · Koń: {{ $entry->horse->name }} @endif
                            @if ($entry->arena) · {{ $entry->arena->name }} @endif
                        </span>
                    </div>
                    <div class="actions">
                        @if ($entry->status === \App\Enums\CalendarEntryStatus::Confirmed)
                            <a class="reschedule" href="{{ route('client_portal.reschedule.show', ['slug' => $tenant->slug, 'entry' => $entry->id]) }}">Przesuń</a>
                        @endif
                        @if ($cancel_links->has($entry->id))
                            <a href="{{ $cancel_links->get($entry->id) }}">Odwołaj</a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="empty">Brak nadchodzących rezerwacji.</div>
            @endforelse
        </section>

        @if ($passes->isNotEmpty())
            <section class="section">
                <h2>Twoje karnety</h2>
                @foreach ($passes as $pass)
                    @php
                        $remaining = (int) $pass->remaining_uses;
                        $total = (int) $pass->total_uses;
                        $percent = $total > 0 ? round(min(100, max(0, $remaining / $total * 100))) : 0;
                    @endphp
                    <div class="pass">
                        <div class="pass-head">
                            <strong>{{ $pass->name }}</strong>
                            @switch($pass->status->value)
                                @case('active')
                                    <span class="pill conf">{{ $pass->status->label() }}</span>
                                    @break
                                @case('exhausted')
                                    <span class="pill complete">{{ $pass->status->label() }}</span>
                                    @break
                                @case('expired')
                                    @case('cancelled')
                                    <span class="pill cancel">{{ $pass->status->label() }}</span>
                                    @break
                                @default
                                    <span class="pill complete">{{ $pass->status->label() }}</span>
                            @endswitch
                        </div>
                        <div class="pass-meta">
                            {{ $remaining }} / {{ $total }} pozostało
                            @if ($pass->valid_until)
                                · ważny do {{ $pass->valid_until->format('d.m.Y') }}
                            @endif
                        </div>
                        <div class="pass-bar"><span style="width: {{ $percent }}%"></span></div>
                    </div>
                @endforeach

                @if ($recent_uses->isNotEmpty())
                    <h3 class="muted">Ostatnio użyte</h3>
                    @foreach ($recent_uses as $use)
                        <div class="use">
                            <span>{{ $use->consumed_at?->format('d.m.Y H:i') }}</span>
                            <span class="muted">
                                @if ($use->calendarEntry)
                                    Lekcja {{ $use->calendarEntry->starts_at->format('d.m.Y') }}
                                @endif
                            </span>
                        </div>
                    @endforeach
                @endif
            </section>
        @endif

        <section class="section">
            <h2>Historia</h2>
            @forelse ($past as $entry)
                <div class="booking">
                    <div class="when">
                        {{ $entry->starts_at->translatedFormat('d.m.Y · H:i') }}
                    </div>
                    <div class="what">
                        @switch($entry->status->value)
                            @case('completed')
                                <span class="pill complete">Zakończona</span>
                                @break
                            @case('cancelled')
                                <span class="pill cancel">Odwołana</span>
                                @break
                            @case('no_show')
                                <span class="pill no">No-show</span>
                                @break
                            @default
                                <span class="pill complete">{{ $entry->status->label() }}</span>
                        @endswitch
                        <span class="meta">
                            {{ $entry->instructor?->name ? 'Instruktor: '.$entry->instructor->name : '' }}
                            @if ($entry->horse) · Koń: {{ $entry->horse->name }} @endif
                        </span>
                    </div>
                    <div class="actions"></div>
                </div>
            @empty
                <div class="empty">Brak historii rezerwacji.</div>
            @endforelse
        </section>

        @if ($unpaid_invoices->isNotEmpty())
            <section class="section">
                <h2>Faktury do opłacenia</h2>
                @foreach ($unpaid_invoices as $invoice)
                    <a class="invoice-row" href="{{ $invoice_links->get($invoice->id) }}">
                        <div>
                            <strong>{{ $invoice->kind->shortLabel() }} {{ $invoice->number }}</strong>
                            <span class="meta">
                                Wystawiona: {{ $invoice->issued_at?->format('d.m.Y') }}
                                @if ($invoice->due_at)
                                    @php $overdue = $invoice->due_at->isPast(); @endphp
                                    · Termin: <span class="{{ $overdue ? 'overdue' : '' }}">{{ $invoice->due_at->format('d.m.Y') }}</span>
                                @endif
                            </span>
                        </div>
                        <div class="invoice-amount">{{ $invoice->totalFormatted() }} →</div>
                    </a>
                @endforeach
            </section>
        @endif

        @if ($recent_messages->isNotEmpty())
            <section class="section">
                <h2>Wiadomości <a href="{{ route('client_portal.messages.show', ['slug' => $tenant->slug]) }}" class="more">Wszystkie →</a></h2>
                @foreach ($recent_messages as $message)
                    <div class="message">
                        <div class="message-head">
                            <strong>{{ $message->subject }}</strong>
                            <span class="muted">{{ $message->sent_at->format('d.m.Y H:i') }}</span>
                        </div>
                        <div class="muted">{{ $message->label() }}</div>
                    </div>
                @endforeach
            </section>
        @endif

        @if ($horses->isNotEmpty())
            <section class="section">
                <h2>Twoje konie</h2>
                @foreach ($horses as $horse)
                    @php
                        $alerts = $horse_alerts->get($horse->id);
                        $overdue = (int) ($alerts->overdue ?? 0);
                        $upcoming30 = (int) ($alerts->upcoming ?? 0);
                    @endphp
                    <a class="horse-row" href="{{ route('client_portal.horses.show', ['slug' => $tenant->slug, 'horse' => $horse->id]) }}">
                        <div>
                            <strong>{{ $horse->name }}</strong>
                            <span class="meta">
                                @if ($horse->breed){{ $horse->breed }}@endif
                                @if ($horse->birth_date) · {{ (int) $horse->birth_date->diffInYears(now()) }} l. @endif
                            </span>
                        </div>
                        <div class="horse-alerts">
                            @if ($overdue > 0)
                                <span class="pill cancel">{{ $overdue }} przeterm.</span>
                            @endif
                            @if ($upcoming30 > 0)
                                <span class="pill conf">{{ $upcoming30 }} w 30 dni</span>
                            @endif
                            @if ($overdue === 0 && $upcoming30 === 0)
                                <span class="pill complete">OK</span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </section>
        @endif
    </div>
</body>
</html>
