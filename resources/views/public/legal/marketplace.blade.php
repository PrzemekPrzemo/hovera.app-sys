@php
    /*
     * Regulamin marketplace transportowego — kluczowy dokument prawny
     * pozycjonujący Hovera jako POŚREDNIKA (NIE przewoźnika). Sekcje
     * renderowane z lang/{locale}/public/legal.php (marketplace.*).
     *
     * Liczba sekcji: 10 (definicje, charakter usługi, rola, odpowiedzialność,
     * weryfikacja, akceptacja oferty, reklamacje, dane osobowe, zmiany, postanowienia).
     */
    $sections = [];
    for ($i = 1; $i <= 10; $i++) {
        $sections[] = [
            'heading' => __('public/legal.marketplace.section_'.$i.'_heading'),
            'body' => __('public/legal.marketplace.section_'.$i.'_body', [
                'company' => config('hovera.legal.company_name'),
                'nip' => config('hovera.legal.nip'),
                'krs' => config('hovera.legal.krs'),
                'address' => config('hovera.legal.address'),
                'court' => config('hovera.legal.court'),
                'support_email' => config('hovera.legal.support_email'),
                'effective_date' => config('hovera.legal.effective_date'),
            ]),
        ];
    }
@endphp

@include('public.legal._legal_layout', [
    'title' => __('public/legal.marketplace.title'),
    'intro' => __('public/legal.marketplace.intro', [
        'company' => config('hovera.legal.company_name'),
    ]),
    'sections' => $sections,
    'active' => 'marketplace',
])
