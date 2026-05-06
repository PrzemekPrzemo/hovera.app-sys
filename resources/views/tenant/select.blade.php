<x-auth-layout title="Wybierz stajnię — Hovera">
    <h1>Wybierz stajnię</h1>
    <p class="muted">
        Twoje konto ma dostęp do {{ $memberships->count() }} stajni. Wybierz, do której chcesz się zalogować.
    </p>

    <form method="post" action="{{ route('tenant.select.choose') }}">
        @csrf
        <div style="display: grid; gap: .5rem; margin-top: .5rem;">
            @foreach ($memberships as $m)
                <label style="display: flex; align-items: center; gap: .75rem; padding: .85rem; border: 1px solid #475569; border-radius: 8px; cursor: pointer;">
                    <input type="radio" name="tenant_id" value="{{ $m->tenant_id }}" @checked($loop->first) required>
                    <div>
                        <div style="font-weight: 600;">{{ $m->tenant->name }}</div>
                        <div style="font-size: .8rem; color: #94a3b8;">{{ $m->tenant->slug }} · rola: {{ $m->role }}</div>
                    </div>
                </label>
            @endforeach
        </div>
        @error('tenant_id')<div class="error">{{ $message }}</div>@enderror
        <button type="submit">Przejdź do stajni</button>
    </form>
</x-auth-layout>
