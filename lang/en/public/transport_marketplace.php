<?php

declare(strict_types=1);

return [
    'meta_title' => 'Open transport board — Hovera Marketplace',
    'meta_description' => 'Public list of open horse transport requests. Verified carriers can submit a quote directly.',

    'banner' => 'All requests are verified. Transport payment — directly to the carrier.',

    'hero_title' => 'Open board — transport requests',
    'hero_subtitle' => 'Live requests from stables, horse owners and other carriers. Submit your quote and win the job.',

    'filter' => [
        'voivodeship_label' => 'Voivodeship',
        'voivodeship_all' => 'All voivodeships',
        'within_days_label' => 'Date window',
        'within_days_any' => 'Any',
        'within_days_option' => 'Within :days days',
        'min_horses_label' => 'Horses',
        'min_horses_any' => 'Any',
        'min_horses_option' => 'At least :count',
        'apply' => 'Apply',
        'clear' => 'Clear filters',
    ],

    'results_meta' => '{0}No open requests match your filters.|{1}1 open request|[2,*]:count open requests',

    'horse_count' => '{1}:count horse|[2,*]:count horses',

    'urgent_pill' => 'Urgent',

    'unknown_voivodeship' => 'unknown',

    'privacy_note' => 'Full address and customer details are revealed after claiming the request in your carrier panel.',

    'submit_quote_cta' => 'Submit a quote',

    'empty' => [
        'heading' => 'No open requests',
        'description' => 'There are no requests matching your filters right now. Try loosening criteria or come back later — new requests appear daily.',
        'cta' => 'Browse carrier directory',
    ],

    'back_to_landing' => '← Back to transport landing',

    'claim' => [
        'lead_unavailable' => 'This request is no longer available (already claimed, expired or cancelled).',
        'not_verified_transporter' => 'You need to be a verified carrier to submit quotes. Register your company and complete verification.',
    ],
];
