<?php

declare(strict_types=1);

return [
    'navigation' => 'Integration status',
    'title' => 'External integration status',
    'hint' => 'Snapshot loads instantly on open. "Check X" runs a live ping (max ~3s, may time out if the external service is down).',

    'status' => [
        'ok' => '✓ OK',
        'degraded' => '⚠ Degraded',
        'error' => '✕ Error',
        'not_configured' => '○ Not configured',
        'unknown' => '? Unknown',
    ],

    'label' => [
        'db_central' => 'Database (central)',
    ],

    'detail' => [
        'configured' => 'Configured (instant check).',
        'live_ok' => 'Live ping OK.',
        'live_no_response' => 'Live ping no response (API returned null).',
        'not_configured_gus' => 'Master admin has not set the GUS key in /admin/company-lookup-settings.',
        'not_configured_ceidg' => 'Master admin has not set the CEIDG token in /admin/company-lookup-settings.',
        'vies_default' => 'Default European Commission endpoint (public).',
        'vies_custom_url' => 'Override URL: :url',
        'nbp_no_cache' => 'No cached rates yet — first fetch will run when a foreign-currency invoice is issued.',
        'nbp_last_sync' => 'Last sync: :code @ :date',
        'ksef_central_missing' => 'Hovera VAT ID or certificate missing in SystemSetting.',
        'smtp_no_host' => 'SMTP host not set — check /admin/smtp-settings.',
        'smtp_host' => 'Host: :host',
        'db_responding' => 'Connection responding (SELECT 1).',
    ],

    'action' => [
        'refresh_all' => 'Refresh snapshot',
        'ping_gus' => 'Check GUS',
        'ping_ceidg' => 'Check CEIDG',
        'ping_vies' => 'Check VIES',
    ],
];
