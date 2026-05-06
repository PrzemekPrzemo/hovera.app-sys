<x-auth-layout title="Aktywuj konto — Hovera">
    <h1>Ustaw hasło</h1>
    <p class="muted">
        @if ($tenant_name)
            Dołączasz do stajni <strong>{{ $tenant_name }}</strong>.
        @endif
        Konto: <strong>{{ $email }}</strong>.
        Wybierz hasło (min. 12 znaków), aby aktywować konto.
    </p>

    <form method="post" action="{{ url('/invite/' . $token) }}">
        @csrf

        <label for="password" style="margin-top: .75rem;">Nowe hasło</label>
        <input id="password" name="password" type="password" required minlength="12" autofocus>
        @error('password')<div class="error">{{ $message }}</div>@enderror

        <label for="password_confirmation" style="margin-top: .75rem;">Powtórz hasło</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required minlength="12">

        <button type="submit">Aktywuj konto i zaloguj</button>
    </form>
</x-auth-layout>
