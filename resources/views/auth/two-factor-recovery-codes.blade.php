<x-auth-layout title="Kody odzyskiwania — Hovera">
    <h1>Zapisz kody odzyskiwania</h1>
    <p class="muted">
        To są jednorazowe kody na wypadek utraty dostępu do aplikacji 2FA.
        <strong>Zapisz je teraz</strong> — nie zobaczysz ich ponownie.
    </p>

    <div class="codes">
        @foreach ($codes as $c)
            <span>{{ $c }}</span>
        @endforeach
    </div>

    <form method="get" action="{{ $return_to ?? url('/app') }}">
        <button type="submit">Mam zapisane — wróć do aplikacji</button>
    </form>
</x-auth-layout>
