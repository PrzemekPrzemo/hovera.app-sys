<x-auth-layout title="Włącz 2FA — Hovera">
    <h1>Włącz uwierzytelnianie dwuskładnikowe</h1>
    <p class="muted">
        Zeskanuj kod aplikacją Google Authenticator (lub kompatybilną), a następnie wpisz
        sześciocyfrowy kod, aby potwierdzić sparowanie.
    </p>

    <div class="qr">{!! $qr_svg !!}</div>

    <p class="muted">Lub wpisz ręcznie:</p>
    <div class="secret">{{ $secret }}</div>

    <form method="post" action="{{ route('two-factor.setup.confirm') }}">
        @csrf
        <label for="code" style="margin-top: 1rem;">Kod z aplikacji</label>
        <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
               pattern="[0-9]{6}" required autofocus>
        @error('code')<div class="error">{{ $message }}</div>@enderror
        <button type="submit">Potwierdź i włącz 2FA</button>
    </form>
</x-auth-layout>
