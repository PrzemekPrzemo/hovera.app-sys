<x-auth-layout :title="__('auth.tenant_select.title')">
    <h1>{{ __('auth.tenant_select.heading') }}</h1>
    <p class="muted">
        {{ __('auth.tenant_select.intro', ['count' => $memberships->count()]) }}
    </p>

    <form method="post" action="{{ route('tenant.select.choose') }}">
        @csrf
        <div style="display: grid; gap: .5rem; margin-top: .5rem;">
            @foreach ($memberships as $m)
                <label style="display: flex; align-items: center; gap: .75rem; padding: .85rem; border: 1px solid #475569; border-radius: 8px; cursor: pointer;">
                    <input type="radio" name="tenant_id" value="{{ $m->tenant_id }}" @checked($loop->first) required>
                    <div>
                        <div style="font-weight: 600;">{{ $m->tenant->name }}</div>
                        <div style="font-size: .8rem; color: #94a3b8;">{{ __('auth.tenant_select.role_label', ['slug' => $m->tenant->slug, 'role' => $m->role]) }}</div>
                    </div>
                </label>
            @endforeach
        </div>
        @error('tenant_id')<div class="error">{{ $message }}</div>@enderror
        <button type="submit">{{ __('auth.tenant_select.submit') }}</button>
    </form>
</x-auth-layout>
