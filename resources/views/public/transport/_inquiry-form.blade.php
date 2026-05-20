{{-- Shared partial: formularz zapytania transportowego.
     Reuse w inquiry.blade.php (strona pełnoekranowa /transport/zapytanie)
     oraz landing.blade.php (sekcja embed na /transport).

     Wymagane zmienne:
       - $old (array)            — domyślne wartości z controllera (old() + pre-fill)
       - $targetTransporter (?)  — Tenant model jeśli direct mode (?transporter=slug)
     Opcjonalne:
       - $formId (string)        — atrybut id formularza (przydatne dla scroll-to)
     --}}
@php($formId = $formId ?? 'tk-inquiry-form')

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

<form method="post" action="{{ route('public.transport.inquiry.submit') }}" id="{{ $formId }}">
    @csrf
    @if (! empty($targetTransporter))
        <input type="hidden" name="transporter" value="{{ $targetTransporter->slug }}">
    @endif

    {{-- Honeypot: ukryte pole „website" filtruje boty. Bona-fide klient nie
         widzi inputa (display:none + tabindex), boty wypełniają wszystko po kolei.
         Server-side: gdy `website` nie jest puste → 200 silent (no lead). --}}
    <div aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">
        <label for="{{ $formId }}-website">Website</label>
        <input type="text" name="website" id="{{ $formId }}-website" tabindex="-1" autocomplete="off" value="">
    </div>

    <div class="row-two">
        <div class="form-row">
            <label for="{{ $formId }}-customer_name">{{ __('public/transport_inquiry.label.customer_name') }}</label>
            <input type="text" name="customer_name" id="{{ $formId }}-customer_name" required maxlength="120" value="{{ $old['customer_name'] }}">
        </div>
        <div class="form-row">
            <label for="{{ $formId }}-customer_email">{{ __('public/transport_inquiry.label.customer_email') }}</label>
            <input type="email" name="customer_email" id="{{ $formId }}-customer_email" required maxlength="255" value="{{ $old['customer_email'] }}">
        </div>
    </div>

    <div class="form-row">
        <label for="{{ $formId }}-customer_phone">{{ __('public/transport_inquiry.label.customer_phone') }}</label>
        <input type="tel" name="customer_phone" id="{{ $formId }}-customer_phone" maxlength="40" value="{{ $old['customer_phone'] }}">
    </div>

    <div class="form-row">
        <label for="{{ $formId }}-pickup_address">{{ __('public/transport_inquiry.label.pickup_address') }}</label>
        <input type="text" name="pickup_address" id="{{ $formId }}-pickup_address" required maxlength="255" value="{{ $old['pickup_address'] }}" placeholder="{{ __('public/transport_inquiry.placeholder.pickup_address') }}" data-places-autocomplete="public" autocomplete="off">
    </div>

    <div class="form-row">
        <label for="{{ $formId }}-dropoff_address">{{ __('public/transport_inquiry.label.dropoff_address') }}</label>
        <input type="text" name="dropoff_address" id="{{ $formId }}-dropoff_address" required maxlength="255" value="{{ $old['dropoff_address'] }}" placeholder="{{ __('public/transport_inquiry.placeholder.dropoff_address') }}" data-places-autocomplete="public" autocomplete="off">
    </div>

    <x-places-autocomplete-script />

    <div class="row-two">
        <div class="form-row">
            <label for="{{ $formId }}-preferred_date">{{ __('public/transport_inquiry.label.preferred_date') }}</label>
            <input type="date" name="preferred_date" id="{{ $formId }}-preferred_date" required value="{{ $old['preferred_date'] }}" min="{{ now()->toDateString() }}">
        </div>
        <div class="form-row">
            <label for="{{ $formId }}-preferred_time">{{ __('public/transport_inquiry.label.preferred_time') }}</label>
            <input type="time" name="preferred_time" id="{{ $formId }}-preferred_time" value="{{ $old['preferred_time'] }}">
        </div>
    </div>

    <label class="checkbox">
        <input type="checkbox" name="flexible_date" value="1">
        <span>{{ __('public/transport_inquiry.label.flexible_date') }}</span>
    </label>

    <div class="form-row">
        <label for="{{ $formId }}-horse_count">{{ __('public/transport_inquiry.label.horse_count') }}</label>
        <input type="number" name="horse_count" id="{{ $formId }}-horse_count" required min="1" max="15" value="{{ $old['horse_count'] }}">
    </div>

    {{-- "Klient zlecenia" picker — pokazywany tylko gdy stable jest
         originator'em I MA aktywnych boarder'ów. Wybranie konkretnego
         boarder'a przekierowuje FV po acceptacji oferty z stajni na
         owner'a (PR 7). Defaultowo: "Stajnia (ja)". --}}
    @if (! empty($boarders ?? []))
        <div class="form-row">
            <label for="{{ $formId }}-client_for">{{ __('public/transport_inquiry.label.client_for') }}</label>
            <select name="client_for" id="{{ $formId }}-client_for">
                <option value="stable" @selected(($old['client_for'] ?? 'stable') === 'stable')>
                    {{ __('public/transport_inquiry.client_for.stable') }}
                </option>
                @foreach ($boarders as $boarder)
                    <option value="boarder:{{ $boarder['id'] }}" @selected(($old['client_for'] ?? '') === 'boarder:'.$boarder['id'])>
                        {{ __('public/transport_inquiry.client_for.boarder_prefix') }}{{ $boarder['label'] }}
                    </option>
                @endforeach
            </select>
            <small style="color:var(--muted);font-size:.78rem;display:block;margin-top:.25rem;">
                {{ __('public/transport_inquiry.client_for.helper') }}
            </small>
        </div>
    @endif

    <div class="form-row">
        <label for="{{ $formId }}-notes">{{ __('public/transport_inquiry.label.notes') }}</label>
        <textarea name="notes" id="{{ $formId }}-notes" maxlength="2000" placeholder="{{ __('public/transport_inquiry.placeholder.notes') }}">{{ $old['notes'] }}</textarea>
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
