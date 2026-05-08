<x-auth-layout :title="__('auth.invitation_accept.title')">
    <h1>{{ __('auth.invitation_accept.heading') }}</h1>
    <p class="muted">
        @if ($tenant_name)
            {!! __('auth.invitation_accept.intro_with_tenant', ['tenant' => e($tenant_name)]) !!}
        @endif
        {!! __('auth.invitation_accept.intro_account', ['email' => e($email)]) !!}
        {{ __('auth.invitation_accept.intro_pwd') }}
    </p>

    <form method="post" action="{{ url('/invite/' . $token) }}">
        @csrf

        <label for="password" style="margin-top: .75rem;">{{ __('auth.invitation_accept.password') }}</label>
        <input id="password" name="password" type="password" required minlength="12" autofocus>
        @error('password')<div class="error">{{ $message }}</div>@enderror

        <label for="password_confirmation" style="margin-top: .75rem;">{{ __('auth.invitation_accept.password_confirmation') }}</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required minlength="12">

        <button type="submit">{{ __('auth.invitation_accept.submit') }}</button>
    </form>
</x-auth-layout>
