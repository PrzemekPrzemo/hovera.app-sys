<?php

declare(strict_types=1);

return [
    'navigation' => 'LiveJumping',
    'title' => 'LiveJumping.com integration',

    'section' => [
        'status' => 'Integration status',
        'status_help' => 'Current state of the partnership with LiveJumping.com. Until active, no LJ UI appears in stable panels.',
        'credentials' => 'API credentials',
        'credentials_help' => 'Provided by the LiveJumping team as part of the partnership agreement. Token is stored encrypted (AES).',
        'partnership' => 'Start partnership',
        'partnership_help' => 'Enable this option after a successful connection test to activate the full integration across all stables.',
    ],

    'field' => [
        'status' => 'Status',
        'connected_at' => 'Connected at',
        'api_url' => 'API URL',
        'api_url_help' => 'Base URL of the LiveJumping partner API, no trailing slash.',
        'api_token' => 'API token',
        'api_token_status' => 'Token saved?',
        'api_token_help' => 'Paste the Bearer token; the existing one will be overwritten. Empty field = no change.',
        'enabled' => 'Activate partnership',
        'enabled_help' => 'When enabled, stable panels show: a "Sport" section in horse and rider cards, an upcoming starts widget on the dashboard, and a competition strip in the calendar.',
    ],

    'status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'configured' => 'configured',
        'not_configured' => 'not configured',
    ],

    'action' => [
        'test' => 'Test connection',
        'test_ok' => 'Connection OK',
        'test_failed' => 'Test failed',
        'test_missing_creds' => 'Missing URL or token — fill them in and try again.',
        'cannot_enable_without_token' => 'Save the API token first to activate.',
        'saved' => 'Settings saved',
        'save_button' => 'Save settings',
    ],
];
