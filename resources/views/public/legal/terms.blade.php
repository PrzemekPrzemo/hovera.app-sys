@php
    $sections = [];
    for ($i = 1; $i <= 11; $i++) {
        $sections[] = [
            'heading' => __('public/legal.terms.section_'.$i.'_heading'),
            'body' => __('public/legal.terms.section_'.$i.'_body'),
        ];
    }
@endphp

@include('public.legal._legal_layout', [
    'title' => __('public/legal.terms.title'),
    'intro' => __('public/legal.terms.intro'),
    'sections' => $sections,
    'active' => 'terms',
])
