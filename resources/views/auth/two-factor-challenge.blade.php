<x-auth-layout :title="__('auth.two_factor.challenge_title')">
    <h1>{{ __('auth.two_factor.challenge_heading') }}</h1>
    <p class="muted">{{ __('auth.two_factor.challenge_intro') }}</p>

    <form method="post" action="{{ route('two-factor.challenge.submit') }}">
        @csrf
        <label for="code">{{ __('auth.two_factor.code_label') }}</label>
        <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
               required autofocus>
        @error('code')<div class="error">{{ $message }}</div>@enderror

        <label style="display:flex;align-items:center;gap:0.5rem;font-weight:normal;margin-top:0.75rem;">
            <input type="checkbox" name="remember_device" value="1">
            <span>{{ __('auth.two_factor.remember_device') }}</span>
        </label>

        <button type="submit">{{ __('auth.two_factor.submit_challenge') }}</button>
    </form>
</x-auth-layout>
