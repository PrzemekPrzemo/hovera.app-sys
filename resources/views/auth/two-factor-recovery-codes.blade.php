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

    <form method="get" action="{{ url('/' . config('hovera.admin.path')) }}">
        <button type="submit">Mam zapisane — przejdź do panelu</button>
    </form>
</x-auth-layout>
