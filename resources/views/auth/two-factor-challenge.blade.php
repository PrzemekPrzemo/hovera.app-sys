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
        <button type="submit">Zaloguj</button>
    </form>
</x-auth-layout>
