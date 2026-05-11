<?php

declare(strict_types=1);

return [
    'navigation' => 'Ads / announcements',

    'model' => [
        'label' => 'Ad',
        'plural' => 'Ads / announcements',
    ],

    'section' => [
        'content' => 'Content',
        'schedule' => 'Activity & schedule',
        'targeting' => 'Targeting',
        'targeting_help' => 'Empty field = no filter (matches everyone). Non-empty = restriction. Selecting specific users (at the bottom) OVERRIDES all other fields — the ad goes ONLY to those users.',
    ],

    'field' => [
        'title' => 'Title (bold in banner)',
        'body' => 'Body',
        'cta_label' => 'CTA button label (optional)',
        'cta_url' => 'CTA target URL (optional)',
        'placement' => 'Placement',
        'variant' => 'Visual variant',
        'is_active' => 'Active',
        'is_active_short' => 'Act.',
        'starts_at' => 'Show from',
        'starts_at_help' => 'Empty = immediately',
        'ends_at' => 'Show until',
        'ends_at_help' => 'Empty = indefinitely',
        'targeting_roles' => 'Roles in stable',
        'targeting_roles_help' => 'Shown only to users with selected role in their stable.',
        'targeting_tenants' => 'Specific stables',
        'targeting_tenants_help' => 'Shown only to users belonging to selected stables.',
        'targeting_countries' => 'Countries (ISO codes)',
        'targeting_countries_help' => 'Filter by tenant.country (e.g. PL, DE, FR). Use two-letter codes.',
        'targeting_locales' => 'User UI language',
        'targeting_locales_help' => 'Filter by user.locale (per-person language preference).',
        'targeting_users' => 'Specific users (override)',
        'targeting_users_help' => 'Selecting one or more users OVERRIDES other filters. The ad reaches ONLY them.',
        'impressions' => 'Impressions',
        'clicks' => 'Clicks',
    ],

    'placement' => [
        'banner' => 'Banner (top of panel)',
        'modal' => 'Modal (pop-up)',
    ],

    'variant' => [
        'info' => 'Info (ochre)',
        'promo' => 'Promo (green)',
        'warning' => 'Warning (orange)',
    ],

    'role' => [
        'instructor' => 'Instructor',
        'employee' => 'Employee',
        'vet' => 'Vet',
        'viewer' => 'Viewer (observer)',
    ],
];
