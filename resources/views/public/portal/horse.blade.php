<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('portal/horse.title', ['horse' => $horse->name, 'tenant' => $tenant->name]) }}</title>
    <meta name="robots" content="noindex">
    <style>
        :root { --primary: {{ $primary_color }}; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; background: #fafafa; color: #3a2f25; }
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
        .pill.gray { background: #f7f4ef; color: #374151; }
        .pill.activity-feeding { background: #d1fae5; color: #065f46; }
        .pill.activity-grooming { background: color-mix(in srgb, var(--primary) 18%, white); color: var(--primary); }
        .pill.activity-turnout { background: #fef3c7; color: #92400e; }
        .pill.activity-exercise { background: color-mix(in srgb, var(--primary) 30%, white); color: var(--primary); }
        .pill.activity-box_cleaning { background: #e0e7ff; color: #3730a3; }
        .pill.activity-transport_event { background: #fee2e2; color: #991b1b; }
        .pill.activity-other { background: #f7f4ef; color: #374151; }
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
        .feeding-meal { padding: .65rem 0; border-bottom: 1px solid #f3f4f6; }
        .feeding-meal:last-of-type { border-bottom: 0; }
        .feeding-meal-head { font-weight: 600; color: var(--primary); margin-bottom: .35rem; font-size: .95rem; }
        .feeding-list { margin: 0; padding-left: 1.25rem; line-height: 1.6; color: #374151; }
        .feeding-list li { padding: .15rem 0; }
        .photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: .65rem; }
        .photo-tile { display: block; position: relative; aspect-ratio: 1 / 1; overflow: hidden; border-radius: 8px; background: #f3f4f6; text-decoration: none; }
        .photo-tile img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .2s ease; }
        .photo-tile:hover img { transform: scale(1.04); }
        .photo-caption { position: absolute; left: 0; right: 0; bottom: 0; padding: .35rem .55rem; background: linear-gradient(transparent, rgba(0,0,0,.65)); color: white; font-size: .8rem; }
        .activity { padding: .8rem 0; border-bottom: 1px solid #f3f4f6; }
        .activity:last-of-type { border-bottom: 0; }
        .activity-head { display: flex; justify-content: space-between; gap: .5rem; align-items: baseline; margin-bottom: .25rem; }
        .activity-head .date { color: #6b7280; font-size: .85rem; white-space: nowrap; }
        .activity-summary { color: #3a2f25; font-size: .95rem; margin-top: .15rem; }
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
        .msg-attachments a:hover { background: #f7f4ef; }
        .msg-from_stable { background: color-mix(in srgb, var(--primary) 5%, white); margin: 0 -.5rem; padding-left: .5rem; padding-right: .5rem; border-radius: 8px; }
        .doc-form { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; padding: 1rem; background: #f9fafb; border-radius: 8px; margin-bottom: 1rem; }
        .doc-form input[type=text], .doc-form select { padding: .55rem .7rem; border: 1px solid #d1d5db; border-radius: 6px; font: inherit; }
        .doc-form input[type=file] { font-size: .85rem; grid-column: 1 / -1; }
        .doc-form button { grid-column: 1 / -1; padding: .65rem 1rem; background: var(--primary); color: white; border: 0; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .doc-form button:hover { filter: brightness(0.95); }
        .doc-form .error { grid-column: 1 / -1; color: #b91c1c; font-size: .85rem; }
        .doc { display: flex; align-items: center; gap: .8rem; padding: .75rem 0; border-bottom: 1px solid #f3f4f6; }
        .doc:last-of-type { border-bottom: 0; }
        .doc-icon { font-size: 1.5rem; flex-shrink: 0; }
        .doc-body { flex: 1; min-width: 0; }
        .doc-body strong { display: block; }
        .doc-body .doc-meta { display: block; color: #6b7280; font-size: .85rem; margin-top: .15rem; }
        .doc-body .doc-meta .overdue { color: #b91c1c; font-weight: 600; }
        .doc-body .doc-meta .soon { color: #b45309; font-weight: 600; }
        .doc-actions { display: flex; gap: .5rem; flex-shrink: 0; }
        .btn-link { font-size: .85rem; padding: .35rem .7rem; background: var(--primary); color: white; border-radius: 6px; text-decoration: none; }
        .btn-link:hover { filter: brightness(0.95); }
        .btn-delete { font-size: .85rem; padding: .35rem .7rem; background: transparent; color: #b91c1c; border: 1px solid #fca5a5; border-radius: 6px; cursor: pointer; }
        .btn-delete:hover { background: #fee2e2; }
        @media (max-width: 540px) {
            .doc-form { grid-template-columns: 1fr; }
            .doc { flex-direction: column; align-items: flex-start; }
        }
        @media (prefers-color-scheme: dark) {
            html:not(.is-demo) body { background: #2a2017; color: #f7f4ef; }
            html:not(.is-demo) .card { background: #3a2f25; }
            html:not(.is-demo) .back { color: #c8b8a4; }
            html:not(.is-demo) .subtitle { color: #c8b8a4; }
            html:not(.is-demo) dl { background: #2a2017; }
            html:not(.is-demo) dt { color: #c8b8a4; }
            html:not(.is-demo) .record { border-color: #5a4d44; }
            html:not(.is-demo) .record .summary { color: #e9e2d3; }
            html:not(.is-demo) .record .meta, .record .head .date { color: #8f8576; }
        }
    </style>
    <x-google-analytics />
</head>
<body>
    <x-demo-light-mode />
    <x-demo-banner />
    <div class="container">
        <a class="back" href="{{ route('client_portal.dashboard', ['slug' => $tenant->slug]) }}">{{ __('portal/horse.back') }}</a>

        <div class="card">
            <h1>{{ $horse->name }}</h1>
            <div class="subtitle">{{ $tenant->name }}</div>

            <dl>
                @if ($horse->breed)<dt>{{ __('portal/horse.info.breed') }}</dt><dd>{{ $horse->breed }}</dd>@endif
                @if ($horse->sex)<dt>{{ __('portal/horse.info.sex') }}</dt><dd>{{ $horse->sex }}</dd>@endif
                @if ($horse->color)<dt>{{ __('portal/horse.info.color') }}</dt><dd>{{ $horse->color }}</dd>@endif
                @if ($horse->birth_date)
                    <dt>{{ __('portal/horse.info.age') }}</dt><dd>{{ __('portal/horse.info.age_value', ['years' => (int) $horse->birth_date->diffInYears(now()), 'year' => $horse->birth_date->format('Y')]) }}</dd>
                @endif
                @if ($horse->microchip)<dt>{{ __('portal/horse.info.microchip') }}</dt><dd>{{ $horse->microchip }}</dd>@endif
                @if ($horse->passport_number)<dt>{{ __('portal/horse.info.passport') }}</dt><dd>{{ $horse->passport_number }}</dd>@endif
            </dl>
        </div>

        @if ($horse->box || $horse->boardingServices->isNotEmpty())
            <div class="card">
                <h2>{{ __('portal/horse.sections.boarding') }}</h2>

                @if ($horse->box)
                    <div class="box-info">
                        <div class="box-pill">
                            {{ __('portal/horse.box.pill', ['label' => $horse->box->label ?: $horse->box->name]) }}
                        </div>
                        <div class="box-meta">
                            {{ $horse->box->typeLabel() }}
                            @if ($horse->box->size_m2) · {{ $horse->box->size_m2 }} m² @endif
                            @if ($horse->box->monthly_rate_cents)
                                · {{ __('portal/horse.box.monthly_label', ['rate' => $horse->box->monthlyRateFormatted()]) }}{{ __('portal/horse.box.monthly_suffix') }}
                            @endif
                        </div>
                    </div>
                @endif

                @if ($horse->boardingServices->isNotEmpty())
                    <h3 class="muted">{{ __('portal/horse.services.heading') }}</h3>
                    <table class="services">
                        <thead>
                        <tr>
                            <th>{{ __('portal/horse.services.col_item') }}</th>
                            <th class="num">{{ __('portal/horse.services.col_price') }}</th>
                            <th>{{ __('portal/horse.services.col_frequency') }}</th>
                            <th class="num">{{ __('portal/horse.services.col_monthly') }}</th>
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
                                <td class="num">{{ __('portal/horse.services.price_per_unit', ['amount' => number_format($unitPrice / 100, 2, ',', ' '), 'unit' => $service->unit]) }}</td>
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
                    <strong>{{ __('portal/horse.cost.monthly_label') }}</strong>
                    <span class="big">{{ number_format($estimated_monthly_cents / 100, 2, ',', ' ') }} zł</span>
                    <small class="muted">{{ __('portal/horse.cost.monthly_disclaimer') }}</small>
                </div>
            </div>
        @endif

        @if ($feeding_plan->isNotEmpty())
            <div class="card">
                <h2>{{ __('portal/horse.sections.feeding_plan') }}</h2>
                @foreach (\App\Enums\FeedingMeal::cases() as $meal)
                    @if ($feeding_plan->has($meal->value))
                        <div class="feeding-meal">
                            <div class="feeding-meal-head">{{ $meal->emoji() }} {{ $meal->label() }}</div>
                            <ul class="feeding-list">
                                @foreach ($feeding_plan->get($meal->value) as $item)
                                    <li>
                                        <strong>{{ $item->feed_type }}</strong>
                                        — {{ $item->amountFormatted() }}
                                        @if ($item->notes)
                                            <span class="muted small">· {{ $item->notes }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach
                <small class="muted">{{ __('portal/horse.feeding_plan.disclaimer') }}</small>
            </div>
        @endif

        @if ($photos->isNotEmpty())
            <div class="card">
                <h2>{{ __('portal/horse.sections.photos') }}</h2>
                <div class="photo-grid">
                    @foreach ($photos as $photo)
                        <a class="photo-tile"
                           href="{{ route('client_portal.horses.photos.view', ['slug' => $tenant->slug, 'horse' => $horse->id, 'photo' => $photo->id]) }}"
                           target="_blank"
                           rel="noopener">
                            <img src="{{ route('client_portal.horses.photos.view', ['slug' => $tenant->slug, 'horse' => $horse->id, 'photo' => $photo->id]) }}"
                                 alt="{{ $photo->caption ?: $horse->name }}"
                                 loading="lazy">
                            @if ($photo->caption)
                                <span class="photo-caption">{{ $photo->caption }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($activities->isNotEmpty())
            <div class="card">
                <h2>{{ __('portal/horse.sections.activities') }}</h2>
                @foreach ($activities as $activity)
                    <div class="activity">
                        <div class="activity-head">
                            <span class="pill activity-{{ $activity->type->value }}">{{ $activity->type->label() }}</span>
                            <span class="date">{{ $activity->performed_at->translatedFormat('d.m.Y · H:i') }}</span>
                        </div>
                        @if ($activity->summary)<div class="activity-summary">{{ $activity->summary }}</div>@endif
                        @if ($activity->details)<div class="muted small">{{ $activity->details }}</div>@endif
                        <div class="activity-meta">
                            @if ($activity->performedByLabel()) {{ $activity->performedByLabel() }} @endif
                            @if ($activity->cost_cents) · <strong>{{ $activity->costFormatted() }}</strong> @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="card">
            <h2>{{ __('portal/horse.sections.messages') }}</h2>
            @if (session('horse_message_sent'))
                <div class="flash">{{ __('portal/horse.messages.sent_flash') }}</div>
            @endif

            <form method="post" enctype="multipart/form-data"
                  action="{{ route('client_portal.horses.messages.send', ['slug' => $tenant->slug, 'horse' => $horse->id]) }}"
                  class="msg-form">
                @csrf
                <input type="text" name="subject" placeholder="{{ __('portal/horse.messages.subject_placeholder') }}" maxlength="200" value="{{ old('subject') }}">
                <textarea name="body" rows="3" placeholder="{{ __('portal/horse.messages.body_placeholder') }}" required maxlength="5000">{{ old('body') }}</textarea>
                <input type="file" name="attachments[]" multiple accept="image/*,application/pdf,.doc,.docx">
                @error('body')<div class="error">{{ $message }}</div>@enderror
                @error('attachments.*')<div class="error">{{ $message }}</div>@enderror
                <button type="submit">{{ __('portal/horse.messages.send') }}</button>
            </form>

            @forelse ($messages as $message)
                <div class="msg msg-{{ $message->direction }}">
                    <div class="msg-head">
                        <strong>
                            @if ($message->isFromStable())
                                {{ $tenant->name }}
                            @else
                                {{ __('portal/horse.messages.you') }}
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
                                ]) }}">📎 {{ $a['original_name'] ?? __('portal/horse.messages.attachment_fallback') }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <div class="empty">{{ __('portal/horse.messages.empty') }}</div>
            @endforelse
        </div>

        <div class="card">
            <h2>{{ __('portal/horse.sections.documents') }}</h2>
            @if (session('horse_document_uploaded'))
                <div class="flash">{{ __('portal/horse.documents.uploaded_flash') }}</div>
            @endif
            @if (session('horse_document_deleted'))
                <div class="flash">{{ __('portal/horse.documents.deleted_flash') }}</div>
            @endif

            <form method="post" enctype="multipart/form-data"
                  action="{{ route('client_portal.horses.documents.upload', ['slug' => $tenant->slug, 'horse' => $horse->id]) }}"
                  class="doc-form">
                @csrf
                <input type="text" name="name" placeholder="{{ __('portal/horse.documents.name_placeholder') }}" required maxlength="200" value="{{ old('name') }}">
                <select name="kind" required>
                    @foreach (\App\Enums\HorseDocumentKind::cases() as $kind)
                        <option value="{{ $kind->value }}" {{ old('kind') === $kind->value ? 'selected' : '' }}>{{ $kind->icon() }} {{ $kind->label() }}</option>
                    @endforeach
                </select>
                <input type="text" name="description" placeholder="{{ __('portal/horse.documents.description_placeholder') }}" maxlength="500" value="{{ old('description') }}">
                <input type="file" name="file" required accept="application/pdf,image/*,.doc,.docx">
                @error('file')<div class="error">{{ $message }}</div>@enderror
                @error('name')<div class="error">{{ $message }}</div>@enderror
                <button type="submit">{{ __('portal/horse.documents.upload') }}</button>
            </form>

            @forelse ($documents as $doc)
                <div class="doc">
                    <div class="doc-icon">{{ $doc->kind->icon() }}</div>
                    <div class="doc-body">
                        <strong>{{ $doc->name }}</strong>
                        <span class="doc-meta">
                            {{ $doc->kind->label() }} · {{ $doc->sizeFormatted() }}
                            · {{ $doc->uploadedByStable() ? __('portal/horse.documents.uploaded_by_stable') : __('portal/horse.documents.uploaded_by_you') }}
                            @if ($doc->valid_until)
                                · {{ __('portal/horse.documents.valid_until') }} <span class="{{ $doc->isExpired() ? 'overdue' : ($doc->isExpiringSoon(30) ? 'soon' : '') }}">
                                    {{ $doc->valid_until->format('d.m.Y') }}
                                </span>
                            @endif
                        </span>
                        @if ($doc->description)<div class="muted small">{{ $doc->description }}</div>@endif
                    </div>
                    <div class="doc-actions">
                        <a href="{{ route('client_portal.horses.documents.download', ['slug' => $tenant->slug, 'horse' => $horse->id, 'document' => $doc->id]) }}"
                           class="btn-link">{{ __('portal/horse.documents.download') }}</a>
                        @if ($doc->uploadedByClient() && $doc->uploaded_by_client_id === $client->id)
                            <form method="post" action="{{ route('client_portal.horses.documents.delete', ['slug' => $tenant->slug, 'horse' => $horse->id, 'document' => $doc->id]) }}" style="display:inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-delete" onclick="return confirm('{{ __('portal/horse.documents.delete_confirm') }}')">{{ __('portal/horse.documents.delete') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="empty">{{ __('portal/horse.documents.empty') }}</div>
            @endforelse
        </div>

        <div class="card">
            <h2>{{ __('portal/horse.sections.health') }}</h2>
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
                        @if ($record->performedByLabel()) {{ __('portal/horse.health.performed_by_label', ['name' => $record->performedByLabel()]) }} @endif
                        @if ($record->next_due_at)
                            · {{ __('portal/horse.health.next_due_label', ['date' => $record->next_due_at->format('d.m.Y')]) }}
                            @if ($isOverdue)
                                <span class="pill danger">{{ __('portal/horse.health.overdue_pill') }}</span>
                            @elseif ($isSoon)
                                <span class="pill warning">{{ __('portal/horse.health.soon_pill') }}</span>
                            @endif
                        @endif
                    </div>
                </div>
            @empty
                <div class="empty">{{ __('portal/horse.health.empty') }}</div>
            @endforelse
        </div>
    </div>
</body>
</html>
