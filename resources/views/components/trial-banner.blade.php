@php
    $tenant = app(\App\Tenancy\TenantManager::class)->current();
    $isTrial = $tenant && $tenant->status === 'trialing';
    $daysLeft = $isTrial && $tenant->trial_ends_at
        ? max(0, (int) now()->startOfDay()->diffInDays($tenant->trial_ends_at, false))
        : null;
@endphp

@if ($isTrial && $daysLeft !== null)
    @php
        $color = $daysLeft <= 3 ? '#b91c1c' : ($daysLeft <= 10 ? '#A8956B' : '#3D2E22');
        $bg = $daysLeft <= 3 ? '#fee2e2' : '#F7F4EF';
    @endphp
    <div style="background: {{ $bg }}; color: {{ $color }}; padding: .55rem 1rem; font-size: .85rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; border-bottom: 1px solid #E9E2D3;">
        <div style="display: flex; align-items: center; gap: .5rem;">
            <span style="font-size: 1rem;">⏳</span>
            <span>
                @if ($daysLeft === 0)
                    <strong>Twój trial kończy się dziś.</strong>
                @elseif ($daysLeft === 1)
                    <strong>Trial kończy się jutro.</strong>
                @else
                    <strong>{{ $daysLeft }} dni</strong> triala pozostało.
                @endif
                Po zakończeniu wybierzesz plan — bez karty kredytowej, możesz zostać na free albo wyjść.
            </span>
        </div>
        <a href="mailto:support@hovera.app?subject=Hovera%20-%20wyb%C3%B3r%20planu%20{{ urlencode($tenant->slug) }}"
           style="background: {{ $color }}; color: white; padding: .35rem .85rem; border-radius: 6px; font-weight: 600; text-decoration: none; font-size: .8rem; white-space: nowrap;">
            Wybierz plan
        </a>
    </div>
@endif
