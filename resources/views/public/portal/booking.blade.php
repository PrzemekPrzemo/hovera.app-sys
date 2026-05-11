<!DOCTYPE html>
<html lang="{{ $tenant->locale ?? 'pl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('portal/booking.title') }} — {{ $tenant->name }}</title>
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
        .form-row { display: grid; gap: .35rem; margin-bottom: 1rem; }
        .form-row label { font-size: .85rem; color: #374151; font-weight: 600; }
        select, input[type=text], textarea { padding: .55rem .7rem; border: 1px solid #d1d5db; border-radius: 6px; font: inherit; width: 100%; }
        select:focus, input:focus, textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 20%, transparent); }
        .empty { color: #9ca3af; font-style: italic; padding: .8rem 0; }
        .slots { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: .5rem; margin-top: .5rem; }
        .slot { padding: .65rem; border: 1px solid #f7f4ef; border-radius: 8px; text-align: center; text-decoration: none; color: #3a2f25; font-weight: 600; background: #fff; cursor: pointer; }
        .slot:hover { background: color-mix(in srgb, var(--primary) 10%, white); border-color: var(--primary); }
        .slot.selected { background: var(--primary); color: white; border-color: var(--primary); }
        .day-list { display: flex; flex-wrap: wrap; gap: .35rem; margin: .5rem 0 1rem; }
        .day-pill { padding: .35rem .65rem; border-radius: 999px; border: 1px solid #f7f4ef; color: #374151; font-size: .8rem; text-decoration: none; }
        .day-pill:hover { background: #f3f4f6; }
        .day-pill.active { background: var(--primary); color: white; border-color: var(--primary); }
        button[type=submit] { padding: .8rem 1.2rem; background: var(--primary); color: white; border: 0; border-radius: 8px; font-weight: 600; font-size: .95rem; cursor: pointer; }
        button[type=submit]:hover { filter: brightness(0.95); }
        button[type=submit]:disabled { background: #d1d5db; cursor: not-allowed; }
        .errors { padding: .8rem 1rem; background: #fef2f2; color: #991b1b; border-radius: 8px; margin-bottom: 1rem; font-size: .9rem; }
        .errors ul { margin: .25rem 0 0; padding-left: 1rem; }
        @media (prefers-color-scheme: dark) {
            html:not(.is-demo) body { background: #2a2017; color: #f7f4ef; }
            html:not(.is-demo) .card { background: #3a2f25; }
            html:not(.is-demo) .back, .subtitle { color: #c8b8a4; }
            html:not(.is-demo) .form-row label { color: #e9e2d3; }
            html:not(.is-demo) select, input[type=text], textarea { background: #2a2017; border-color: #5a4d44; color: #f7f4ef; }
            html:not(.is-demo) .slot { background: #2a2017; border-color: #5a4d44; color: #f7f4ef; }
            html:not(.is-demo) .day-pill { background: #2a2017; border-color: #5a4d44; color: #f7f4ef; }
        }
    </style>
</head>
<body>
    <x-demo-light-mode />
    <x-demo-banner />
    <div class="container">
        <a class="back" href="{{ route('client_portal.dashboard', ['slug' => $tenant->slug]) }}">
            {{ __('portal/booking.back') }}
        </a>

        <div class="card">
            <h1>{{ __('portal/booking.heading') }}</h1>
            <div class="subtitle">{{ __('portal/booking.subtitle', ['tenant' => $tenant->name]) }}</div>

            @if ($errors->any())
                <div class="errors">
                    <strong>{{ __('portal/booking.errors_heading') }}</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($horses->isEmpty())
                <p class="empty">{{ __('portal/booking.no_horses') }}</p>
            @else
                {{-- Step 1: pick horse + instructor (form GET refreshes slots) --}}
                <form method="get" action="{{ route('client_portal.book.show', ['slug' => $tenant->slug]) }}">
                    <div class="form-row">
                        <label for="horse_id_pick">{{ __('portal/booking.label.horse') }}</label>
                        <select name="horse_pick" id="horse_id_pick" disabled>
                            @foreach ($horses as $h)
                                <option value="{{ $h->id }}">{{ $h->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-row">
                        <label for="instructor_pick">{{ __('portal/booking.label.instructor') }}</label>
                        <select name="instructor" id="instructor_pick" onchange="this.form.submit()">
                            <option value="">{{ __('portal/booking.label.instructor_placeholder') }}</option>
                            @foreach ($instructors as $i)
                                <option value="{{ $i->id }}" {{ $selected_instructor && $selected_instructor->id === $i->id ? 'selected' : '' }}>
                                    {{ $i->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>

                @if ($selected_instructor)
                    <div class="form-row">
                        <label>{{ __('portal/booking.label.day') }}</label>
                        @if ($dates_with_slots->isEmpty())
                            <p class="empty">{{ __('portal/booking.no_dates') }}</p>
                        @else
                            <div class="day-list">
                                @foreach ($dates_with_slots as $d)
                                    @php $key = $d->toDateString(); @endphp
                                    <a class="day-pill {{ $key === $date ? 'active' : '' }}"
                                       href="{{ route('client_portal.book.show', ['slug' => $tenant->slug, 'instructor' => $selected_instructor->id, 'date' => $key]) }}">
                                        {{ $d->translatedFormat('EE d.m') }}
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if ($slots->isNotEmpty())
                        <form method="post" action="{{ route('client_portal.book.submit', ['slug' => $tenant->slug]) }}">
                            @csrf
                            <input type="hidden" name="instructor_id" value="{{ $selected_instructor->id }}">

                            <div class="form-row">
                                <label for="horse_id">{{ __('portal/booking.label.horse_for') }}</label>
                                <select name="horse_id" id="horse_id" required>
                                    @foreach ($horses as $h)
                                        <option value="{{ $h->id }}" {{ old('horse_id') === $h->id ? 'selected' : '' }}>{{ $h->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-row">
                                <label>{{ __('portal/booking.label.slot') }}</label>
                                <div class="slots">
                                    @foreach ($slots as $slot)
                                        <label class="slot">
                                            <input type="radio" name="starts_at" value="{{ $slot->toIso8601String() }}" required style="display:none"
                                                   onchange="document.querySelectorAll('.slot').forEach(s=>s.classList.remove('selected'));this.parentElement.classList.add('selected')">
                                            {{ $slot->format('H:i') }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div class="form-row">
                                <label for="notes">{{ __('portal/booking.label.notes') }}</label>
                                <textarea name="notes" id="notes" rows="2" maxlength="500" placeholder="{{ __('portal/booking.label.notes_placeholder') }}">{{ old('notes') }}</textarea>
                            </div>

                            <button type="submit">{{ __('portal/booking.actions.submit') }}</button>
                        </form>
                    @elseif ($selected_instructor)
                        <p class="empty">{{ __('portal/booking.no_slots') }}</p>
                    @endif
                @endif
            @endif
        </div>
    </div>
</body>
</html>
