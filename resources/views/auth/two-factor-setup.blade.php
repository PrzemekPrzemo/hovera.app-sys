<x-auth-layout :title="__('auth.two_factor.setup_title')">
    <h1>{{ __('auth.two_factor.setup_heading') }}</h1>
    <p class="muted">{{ __('auth.two_factor.setup_intro') }}</p>

    <div class="qr">{!! $qr_svg !!}</div>

    <p class="muted">{{ __('auth.two_factor.manual_entry') }}</p>
    <div class="secret">{{ $secret }}</div>

    <form method="post" action="{{ route('two-factor.setup.confirm') }}">
        @csrf
        <label for="code" style="margin-top: 1rem;">{{ __('auth.two_factor.code_label') }}</label>
        <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
               pattern="[0-9]{6}" required autofocus>
        @error('code')<div class="error">{{ $message }}</div>@enderror
        <button type="submit">{{ __('auth.two_factor.confirm') }}</button>
    </form>
</x-auth-layout>
