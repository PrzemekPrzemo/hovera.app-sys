<x-auth-layout :title="__('auth.two_factor.recovery_codes_title')">
    <h1>{{ __('auth.two_factor.recovery_codes_heading') }}</h1>
    <p class="muted">{{ __('auth.two_factor.recovery_codes_intro') }}</p>

    <div class="codes">
        @foreach ($codes as $c)
            <span>{{ $c }}</span>
        @endforeach
    </div>

    <form method="get" action="{{ $return_to ?? url('/app') }}">
        <button type="submit">{{ __('auth.two_factor.recovery_codes_continue') }}</button>
    </form>
</x-auth-layout>
