<x-auth-layout title="Brak dostępnych stajni — Hovera">
    <h1>Brak dostępnych stajni</h1>
    <p class="muted">
        Twoje konto nie jest jeszcze przypisane do żadnej stajni, lub Twój dostęp został cofnięty.
        Skontaktuj się z administratorem stajni, aby uzyskać dostęp.
    </p>

    <form method="post" action="{{ url('/app/logout') }}">
        @csrf
        <button type="submit">Wyloguj się</button>
    </form>
</x-auth-layout>
