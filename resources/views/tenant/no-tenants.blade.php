<x-auth-layout :title="__('auth.no_tenants.title')">
    <h1>{{ __('auth.no_tenants.heading') }}</h1>
    <p class="muted">{{ __('auth.no_tenants.intro') }}</p>

    <form method="post" action="{{ url('/app/logout') }}">
        @csrf
        <button type="submit">{{ __('auth.no_tenants.logout') }}</button>
    </form>
</x-auth-layout>
