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
        $proUrl = route('billing.show', ['plan' => 'pro']);
        $maxHorses = (int) ($tenant->trial_max_horses ?? 10);
        $maxClients = (int) ($tenant->trial_max_clients ?? 5);
    @endphp
    <div style="background: {{ $bg }}; color: {{ $color }}; padding: .55rem 1rem; font-size: .85rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; border-bottom: 1px solid #E9E2D3; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: .5rem; min-width: 0;">
            <span style="font-size: 1rem;">⏳</span>
            <span>
                @if ($daysLeft === 0)
                    <strong>{{ __('billing.trial_banner.expires_today') }}</strong>
                @elseif ($daysLeft === 1)
                    <strong>{{ __('billing.trial_banner.expires_tomorrow') }}</strong>
                @else
                    <strong>{{ trans_choice('billing.trial_banner.days_left', $daysLeft, ['days' => $daysLeft]) }}</strong>
                @endif
                {{ __('billing.trial_banner.pro_pitch', ['horses' => $maxHorses, 'clients' => $maxClients]) }}
            </span>
        </div>
        <a href="{{ $proUrl }}"
           style="background: {{ $color }}; color: white; padding: .35rem .85rem; border-radius: 6px; font-weight: 600; text-decoration: none; font-size: .8rem; white-space: nowrap;">
            {{ __('billing.trial_banner.cta_pro') }}
        </a>
    </div>
@endif
