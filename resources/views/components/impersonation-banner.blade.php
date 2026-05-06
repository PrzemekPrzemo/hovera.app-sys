@php
    $sessionId = session('impersonation.session_id');
    $expiresAt = session('impersonation.expires_at');
@endphp

@if ($sessionId)
    <div style="background: #b91c1c; color: #fee2e2; padding: .65rem 1rem; font-size: .85rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; border-bottom: 1px solid #7f1d1d;">
        <div style="display: flex; align-items: center; gap: .5rem;">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            <span>
                <strong>Tryb impersonacji:</strong> jesteś zalogowany jako
                <strong>{{ auth()->user()?->email }}</strong>.
                @if ($expiresAt)
                    Sesja wygasa <span title="{{ $expiresAt }}">{{ \Carbon\Carbon::parse($expiresAt)->diffForHumans() }}</span>.
                @endif
                Każda akcja jest zapisywana w audit logu stajni.
            </span>
        </div>
        <form method="post" action="{{ route('impersonation.stop') }}" style="margin: 0;">
            @csrf
            <button type="submit" style="background: #fff; color: #b91c1c; border: 0; padding: .35rem .8rem; border-radius: 6px; font-size: .85rem; font-weight: 600; cursor: pointer;">
                Zakończ impersonację
            </button>
        </form>
    </div>
@endif
