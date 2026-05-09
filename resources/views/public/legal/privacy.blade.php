@php
    $sections = [];
    for ($i = 1; $i <= 9; $i++) {
        $sections[] = [
            'heading' => __('public/legal.privacy.section_'.$i.'_heading'),
            'body' => __('public/legal.privacy.section_'.$i.'_body'),
        ];
    }
@endphp

@include('public.legal._legal_layout', [
    'title' => __('public/legal.privacy.title'),
    'intro' => __('public/legal.privacy.intro'),
    'sections' => $sections,
    'active' => 'privacy',
])
