<x-auth-layout :title="__('admin/back-office.suspended.title')">
    <h1>{{ __('admin/back-office.suspended.heading') }}</h1>
    <p class="muted">
        {{ __('admin/back-office.suspended.intro', ['name' => $tenant?->name ?? '—']) }}
    </p>

    @if ($tenant?->suspended_reason)
        <div class="rounded-md bg-amber-50 p-3 text-sm text-amber-800 mt-4">
            <strong>{{ __('admin/back-office.suspended.reason') }}:</strong>
            {{ $tenant->suspended_reason }}
        </div>
    @endif

    <p class="mt-4 text-sm">
        {{ __('admin/back-office.suspended.contact') }}
        <a href="mailto:support@hovera.app" class="text-primary-600 hover:underline">support@hovera.app</a>
    </p>

    <div class="mt-6 flex gap-2">
        <a href="{{ route('billing.show') }}" class="filament-button">
            {{ __('admin/back-office.suspended.go_billing') }}
        </a>
        <form method="post" action="{{ url('/app/logout') }}">
            @csrf
            <button type="submit" class="filament-button">{{ __('admin/back-office.suspended.logout') }}</button>
        </form>
    </div>
</x-auth-layout>
