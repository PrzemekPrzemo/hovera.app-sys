@php
    $sections = [];
    for ($i = 1; $i <= 10; $i++) {
        $sections[] = [
            'heading' => __('public/legal.dpa.section_'.$i.'_heading'),
            'body' => __('public/legal.dpa.section_'.$i.'_body'),
        ];
    }
@endphp

@include('public.legal._legal_layout', [
    'title' => __('public/legal.dpa.title'),
    'intro' => __('public/legal.dpa.intro'),
    'sections' => $sections,
    'active' => 'dpa',
])
