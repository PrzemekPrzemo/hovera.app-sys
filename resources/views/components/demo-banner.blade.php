@php
    $isDemo = session('demo.is_demo') === true;
@endphp

@if ($isDemo)
    <div style="background: linear-gradient(90deg, #A8956B, #8F8576); color: #1F1A17; padding: .65rem 1rem; font-size: .85rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; border-bottom: 2px solid #3D2E22;">
        <div style="display: flex; align-items: center; gap: .65rem;">
            <span style="font-size: 1.1rem;">🎬</span>
            <span>
                <strong>Tryb demo</strong> — możesz wszystko zmieniać.
                Dane resetują się <strong>codziennie o 22:00</strong>.
                Spróbuj edytować, dodać konia, wystawić fakturę — nic się nie zepsuje.
            </span>
        </div>
        <div style="display: flex; gap: .35rem; flex-wrap: wrap;">
            <span style="font-size: .75rem; color: #3D2E22; opacity: .85;">Zaloguj jako:</span>
            @foreach (['owner' => 'Owner', 'manager' => 'Manager', 'instructor' => 'Trener', 'employee' => 'Pracownik', 'vet' => 'Weterynarz', 'viewer' => 'Viewer'] as $role => $label)
                <a href="{{ url('/demo/as/'.$role) }}"
                   style="background: rgba(255,255,255,.85); color: #3D2E22; padding: .2rem .55rem; border-radius: 999px; font-size: .72rem; font-weight: 600; text-decoration: none; border: 1px solid rgba(61,46,34,.2);">
                    {{ $label }}
                </a>
            @endforeach
            <a href="{{ url('/demo/as-client') }}"
               title="Panel klienta — widok właściciela konia (portal /s/demo/portal)"
               style="background: #3D2E22; color: #F7F4EF; padding: .2rem .55rem; border-radius: 999px; font-size: .72rem; font-weight: 600; text-decoration: none; border: 1px solid #3D2E22;">
                Klient (właściciel konia)
            </a>
        </div>
    </div>
@endif
