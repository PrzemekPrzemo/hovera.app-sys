<x-auth-layout title="Weryfikacja 2FA — Hovera">
    <h1>Wpisz kod 2FA</h1>
    <p class="muted">
        Wpisz sześciocyfrowy kod z aplikacji uwierzytelniającej, lub kod jednorazowy
        z listy kodów odzyskiwania.
    </p>

    <form method="post" action="{{ route('two-factor.challenge.submit') }}">
        @csrf
        <label for="code">Kod</label>
        <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
               required autofocus>
        @error('code')<div class="error">{{ $message }}</div>@enderror

        <label style="display:flex;align-items:center;gap:0.5rem;font-weight:normal;margin-top:0.75rem;">
            <input type="checkbox" name="remember_device" value="1">
            <span>Zapamiętaj to urządzenie na 14 dni</span>
        </label>

        <button type="submit">Zaloguj</button>
    </form>
</x-auth-layout>
