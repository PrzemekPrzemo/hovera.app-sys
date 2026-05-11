@php
    $user = auth()->user();
    if (! $user) {
        return;
    }

    $resolver = app(\App\Services\Ads\MasterAdResolver::class);
    $ads = $resolver->forUser($user);
    if ($ads->isEmpty()) {
        return;
    }

    foreach ($ads as $ad) {
        $resolver->trackImpression($ad);
    }

    $variantColors = [
        'info' => ['bg' => '#E9E2D3', 'border' => '#A8956B', 'text' => '#3D2E22'],
        'promo' => ['bg' => '#d1fae5', 'border' => '#065f46', 'text' => '#064e3b'],
        'warning' => ['bg' => '#fef3c7', 'border' => '#b45309', 'text' => '#78350f'],
    ];
@endphp

@foreach ($ads as $ad)
    @php $c = $variantColors[$ad->variant] ?? $variantColors['info']; @endphp
    <div data-master-ad="{{ $ad->id }}"
         style="background: {{ $c['bg'] }}; color: {{ $c['text'] }}; border-bottom: 2px solid {{ $c['border'] }}; padding: .75rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; font-size: .9rem;">
        <div style="flex: 1; min-width: 0;">
            <strong style="display: block;">{{ $ad->title }}</strong>
            <span style="opacity: .85;">{{ $ad->body }}</span>
        </div>
        <div style="display: flex; gap: .5rem; align-items: center; flex-shrink: 0;">
            @if ($ad->cta_url && $ad->cta_label)
                <a href="{{ route('master-ads.click', ['ad' => $ad->id]) }}"
                   style="background: {{ $c['border'] }}; color: #fff; padding: .35rem .75rem; border-radius: 6px; text-decoration: none; font-weight: 600;">
                    {{ $ad->cta_label }}
                </a>
            @endif
            <form method="post" action="{{ route('master-ads.dismiss', ['ad' => $ad->id]) }}" style="margin: 0;">
                @csrf
                <button type="submit"
                        title="{{ __('common.dismiss') }}"
                        style="background: transparent; border: 0; color: {{ $c['text'] }}; opacity: .6; cursor: pointer; font-size: 1.1rem; line-height: 1;">×</button>
            </form>
        </div>
    </div>
@endforeach
