<?php

declare(strict_types=1);

return [
    'tokens' => [
        'navigation' => 'My API tokens',
        'title' => 'Master admin personal API tokens',
        'col' => [
            'name' => 'Name',
            'abilities' => 'Abilities',
            'last_used_at' => 'Last used',
            'created_at' => 'Created',
            'expires_at' => 'Expires',
            'never' => 'never',
        ],
        'action' => [
            'generate' => 'Generate token',
            'generate_submit' => 'Generate',
            'revoke' => 'Revoke',
            'revoke_confirm' => 'Token will stop working immediately. All scripts using it will receive 401.',
            'revoke_success' => 'Token revoked',
        ],
        'form' => [
            'name' => 'Token name',
            'name_placeholder' => 'e.g. Monitoring script',
            'abilities' => 'Abilities (scopes)',
            'abilities_help' => 'Pick the minimum required. "admin-all" grants full access.',
            'expiry' => 'Expiry',
            'expiry_none' => 'No expiry',
            'expiry_30d' => '30 days',
            'expiry_90d' => '90 days',
            'expiry_1y' => '1 year',
        ],
        'abilities' => [
            'read-tenants' => 'Read tenants (read-tenants)',
            'read-billing' => 'Read billing/Stripe (read-billing)',
            'read-system' => 'Read system metrics (read-system)',
            'admin-impersonate' => 'Impersonate users (admin-impersonate)',
            'admin-all' => 'Full admin access (admin-all)',
        ],
        'modal' => [
            'heading' => 'Token generated',
            'warning' => 'Copy it now — you will not see it again. If lost, generate a new one.',
            'name_label' => 'Token',
            'copy' => 'Copy to clipboard',
        ],
    ],

    'tenant_tokens' => [
        'navigation' => 'Tenant API tokens',
        'title' => 'API tokens issued to tenants',
        'col' => [
            'user' => 'User',
            'tenant' => 'Tenant',
            'name' => 'Token name',
            'abilities' => 'Abilities',
            'last_used_at' => 'Last used',
            'created_at' => 'Created',
            'ip' => 'IP',
            'user_agent' => 'User-Agent',
        ],
        'filter' => [
            'tenant' => 'Tenant',
            'activity' => 'Activity',
            'active_30d' => 'Active (30 days)',
            'dormant' => 'Dormant (no activity)',
            'any' => 'Any',
            'created_range' => 'Created range',
        ],
        'action' => [
            'revoke' => 'Revoke',
            'revoke_confirm' => 'Token will stop working immediately. The user’s mobile app will have to log in again.',
            'revoke_success' => 'Token revoked',
        ],
        'bulk' => [
            'revoke' => 'Revoke selected',
            'revoked' => 'Revoked :count tokens',
        ],
    ],

    'webhooks' => [
        'navigation' => 'Tenant webhooks',
        'model' => 'Webhook subscription',
        'model_plural' => 'Webhooks',
        'col' => [
            'tenant' => 'Tenant',
            'url_host' => 'URL host',
            'events' => 'Events',
            'is_active' => 'Active',
            'last_delivery' => 'Last delivery',
            'last_delivery_at' => 'Last delivery at',
            'created_at' => 'Created',
        ],
        'form' => [
            'section' => [
                'target' => 'Endpoint and events',
                'signing' => 'Request signing',
            ],
            'tenant' => 'Tenant',
            'is_active' => 'Active',
            'url' => 'Endpoint URL',
            'url_help' => 'POST to this URL when any of the selected events fires. HTTPS recommended.',
            'events' => 'Events',
            'secret' => 'HMAC secret',
            'secret_regenerated' => 'New secret generated',
            'signing_help' => 'Each request carries X-Hovera-Signature: sha256=<hex> computed via HMAC over the body. Receivers must verify the signature with the same secret.',
        ],
        'filter' => [
            'tenant' => 'Tenant',
            'is_active' => 'Active',
        ],
        'action' => [
            'enable' => 'Enable',
            'disable' => 'Disable',
            'toggled' => 'State changed',
        ],
        'deliveries' => [
            'title' => 'Delivery history (last 50)',
            'col' => [
                'event' => 'Event',
                'attempt' => 'Attempt',
                'status' => 'HTTP status',
                'duration' => 'Duration',
                'delivered_at' => 'Delivered',
                'error' => 'Error',
                'payload' => 'Payload',
            ],
            'action' => [
                'resend' => 'Resend',
                'resent' => 'Resend queued',
            ],
        ],
    ],
];
