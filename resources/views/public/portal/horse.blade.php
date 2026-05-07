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
        .pill.activity-feeding { background: #d1fae5; color: #065f46; }
        .pill.activity-grooming { background: color-mix(in srgb, var(--primary) 18%, white); color: var(--primary); }
        .pill.activity-turnout { background: #fef3c7; color: #92400e; }
        .pill.activity-exercise { background: color-mix(in srgb, var(--primary) 30%, white); color: var(--primary); }
        .pill.activity-box_cleaning { background: #e0e7ff; color: #3730a3; }
        .pill.activity-transport_event { background: #fee2e2; color: #991b1b; }
        .pill.activity-other { background: #e5e7eb; color: #374151; }
        .box-info { display: flex; align-items: center; gap: 1rem; padding: 1rem; background: #f9fafb; border-radius: 8px; margin-bottom: 1rem; }
        .box-info .box-pill { background: var(--primary); color: white; padding: .5rem 1rem; border-radius: 8px; font-weight: 700; }
        .box-info .box-meta { color: #6b7280; font-size: .9rem; }
        .services { width: 100%; border-collapse: collapse; margin-top: .5rem; font-size: .9rem; }
        .services th, .services td { padding: .5rem .65rem; border-bottom: 1px solid #f3f4f6; text-align: left; }
        .services th { background: #f9fafb; color: #374151; font-weight: 600; font-size: .8rem; text-transform: uppercase; }
        .services td.num, .services th.num { text-align: right; }
        .services .meta { display: block; color: #9ca3af; font-size: .8rem; }
        .cost-summary { padding: 1rem; background: color-mix(in srgb, var(--primary) 10%, white); border-radius: 8px; margin-top: 1rem; display: flex; flex-direction: column; gap: .15rem; }
        .cost-summary .big { font-size: 1.4rem; font-weight: 700; color: var(--primary); }
        .activity { padding: .8rem 0; border-bottom: 1px solid #f3f4f6; }
        .activity:last-of-type { border-bottom: 0; }
        .activity-head { display: flex; justify-content: space-between; gap: .5rem; align-items: baseline; margin-bottom: .25rem; }
        .activity-head .date { color: #6b7280; font-size: .85rem; white-space: nowrap; }
        .activity-summary { color: #1f2937; font-size: .95rem; margin-top: .15rem; }
        .activity-meta { color: #9ca3af; font-size: .85rem; margin-top: .25rem; }
        .small { font-size: .85rem; line-height: 1.4; }
        h3.muted { font-size: .85rem; color: #6b7280; margin: 1rem 0 .35rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
        .muted { color: #6b7280; }
        .flash { background: #d1fae5; color: #065f46; padding: .65rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: .9rem; }
        .msg-form { display: grid; gap: .5rem; padding: 1rem; background: #f9fafb; border-radius: 8px; margin-bottom: 1rem; }
        .msg-form input[type=text], .msg-form textarea { padding: .55rem .7rem; border: 1px solid #d1d5db; border-radius: 6px; font: inherit; }
        .msg-form input[type=file] { font-size: .85rem; }
        .msg-form button { padding: .65rem 1rem; background: var(--primary); color: white; border: 0; border-radius: 6px; font-weight: 600; cursor: pointer; align-self: end; }
        .msg-form button:hover { filter: brightness(0.95); }
        .msg-form .error { color: #b91c1c; font-size: .85rem; }
        .msg { padding: .9rem 0; border-bottom: 1px solid #f3f4f6; }
        .msg:last-of-type { border-bottom: 0; }
        .msg-head { display: flex; justify-content: space-between; gap: .5rem; align-items: baseline; margin-bottom: .25rem; }
        .msg-head strong { font-size: .95rem; }
        .msg-head .date { color: #6b7280; font-size: .85rem; white-space: nowrap; }
        .msg-subject { font-weight: 600; margin-bottom: .25rem; }
        .msg-body { white-space: pre-wrap; line-height: 1.5; }
        .msg-attachments { margin-top: .5rem; display: flex; flex-wrap: wrap; gap: .5rem; }
        .msg-attachments a { font-size: .85rem; padding: .2rem .55rem; background: #f3f4f6; border-radius: 4px; text-decoration: none; color: var(--primary); }
        .msg-attachments a:hover { background: #e5e7eb; }
        .msg-from_stable { background: color-mix(in srgb, var(--primary) 5%, white); margin: 0 -.5rem; padding-left: .5rem; padding-right: .5rem; border-radius: 8px; }
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

        @if ($horse->box || $horse->boardingServices->isNotEmpty())
            <div class="card">
                <h2>Pensja i koszty</h2>

                @if ($horse->box)
                    <div class="box-info">
                        <div class="box-pill">
                            🏠 Box {{ $horse->box->label ?: $horse->box->name }}
                        </div>
                        <div class="box-meta">
                            {{ $horse->box->typeLabel() }}
                            @if ($horse->box->size_m2) · {{ $horse->box->size_m2 }} m² @endif
                            @if ($horse->box->monthly_rate_cents)
                                · pensjonat: {{ $horse->box->monthlyRateFormatted() }}/mies.
                            @endif
                        </div>
                    </div>
                @endif

                @if ($horse->boardingServices->isNotEmpty())
                    <h3 class="muted">Naliczane usługi</h3>
                    <table class="services">
                        <thead>
                        <tr>
                            <th>Pozycja</th>
                            <th class="num">Cena</th>
                            <th>Częstotliwość</th>
                            <th class="num">~mies.</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($horse->boardingServices as $service)
                            @php
                                $unitPrice = (int) ($service->pivot->price_override_cents ?? $service->price_cents);
                                $qty = (float) ($service->pivot->quantity ?? 1);
                                $monthly = (int) round($unitPrice * $qty * $service->frequency->monthlyMultiplier());
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $service->name }}</strong>
                                    @if ($qty > 1)
                                        <span class="muted"> · {{ rtrim(rtrim(number_format($qty, 3, ',', ' '), '0'), ',') }} {{ $service->unit }}</span>
                                    @endif
                                    @if ($service->description)
                                        <span class="meta">{{ $service->description }}</span>
                                    @endif
                                </td>
                                <td class="num">{{ number_format($unitPrice / 100, 2, ',', ' ') }} zł / {{ $service->unit }}</td>
                                <td>{{ $service->frequency->label() }}</td>
                                <td class="num">
                                    @if ($monthly > 0)
                                        {{ number_format($monthly / 100, 2, ',', ' ') }} zł
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif

                <div class="cost-summary">
                    <strong>Szacunkowy koszt miesięczny:</strong>
                    <span class="big">{{ number_format($estimated_monthly_cents / 100, 2, ',', ' ') }} zł</span>
                    <small class="muted">Bez usług "za użycie" i jednorazowych — te pojawiają się tylko gdy są naliczane.</small>
                </div>
            </div>
        @endif

        @if ($activities->isNotEmpty())
            <div class="card">
                <h2>Co robimy z Twoim koniem</h2>
                @foreach ($activities as $activity)
                    <div class="activity">
                        <div class="activity-head">
                            <span class="pill activity-{{ $activity->type->value }}">{{ $activity->type->label() }}</span>
                            <span class="date">{{ $activity->performed_at->translatedFormat('d.m.Y · H:i') }}</span>
                        </div>
                        @if ($activity->summary)<div class="activity-summary">{{ $activity->summary }}</div>@endif
                        @if ($activity->details)<div class="muted small">{{ $activity->details }}</div>@endif
                        <div class="activity-meta">
                            @if ($activity->performed_by) {{ $activity->performed_by }} @endif
                            @if ($activity->cost_cents) · <strong>{{ $activity->costFormatted() }}</strong> @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="card">
            <h2>Wiadomości ze stajni</h2>
            @if (session('horse_message_sent'))
                <div class="flash">✓ Wiadomość wysłana — stajnia dostała powiadomienie e-mail.</div>
            @endif

            <form method="post" enctype="multipart/form-data"
                  action="{{ route('client_portal.horses.messages.send', ['slug' => $tenant->slug, 'horse' => $horse->id]) }}"
                  class="msg-form">
                @csrf
                <input type="text" name="subject" placeholder="Temat (opcjonalnie)" maxlength="200" value="{{ old('subject') }}">
                <textarea name="body" rows="3" placeholder="Napisz coś do stajni…" required maxlength="5000">{{ old('body') }}</textarea>
                <input type="file" name="attachments[]" multiple accept="image/*,application/pdf,.doc,.docx">
                @error('body')<div class="error">{{ $message }}</div>@enderror
                @error('attachments.*')<div class="error">{{ $message }}</div>@enderror
                <button type="submit">Wyślij</button>
            </form>

            @forelse ($messages as $message)
                <div class="msg msg-{{ $message->direction }}">
                    <div class="msg-head">
                        <strong>
                            @if ($message->isFromStable())
                                {{ $tenant->name }}
                            @else
                                Ty
                            @endif
                        </strong>
                        <span class="date">{{ $message->sent_at->translatedFormat('d.m.Y · H:i') }}</span>
                    </div>
                    @if ($message->subject)<div class="msg-subject">{{ $message->subject }}</div>@endif
                    <div class="msg-body">{!! nl2br(e($message->body)) !!}</div>
                    @if ($message->attachmentCount() > 0)
                        <div class="msg-attachments">
                            @foreach ((array) $message->attachments as $i => $a)
                                <a href="{{ route('client_portal.horses.messages.attachment', [
                                    'slug' => $tenant->slug,
                                    'horse' => $horse->id,
                                    'message' => $message->id,
                                    'index' => $i,
                                ]) }}">📎 {{ $a['original_name'] ?? 'załącznik' }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <div class="empty">Brak wiadomości — napisz pierwszą.</div>
            @endforelse
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
