<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'identification' => 'Identification',
            'location' => 'Location',
            'subscription' => 'Subscription',
            'branding' => 'Branding',
            'branding_description' => 'Used on the public page /s/{slug} and in emails.',
            'public_profile' => 'Public profile',
            'public_profile_description' => 'Information shown on the public stable page /s/{slug}.',
            'database' => 'Database',
        ],
        'label' => [
            'type' => 'Tenant type',
            'tax_id' => 'Tax ID / VAT ID',
            'plan' => 'Plan',
            'primary_color' => 'Primary color',
            'logo_url' => 'Logo URL',
            'public_description' => 'Stable description',
            'public_email' => 'Contact email (public)',
            'public_phone' => 'Contact phone',
            'public_address' => 'Address',
            'public_website' => 'Website',
        ],
        'option' => [
            'type' => [
                'stable' => 'Equestrian stable',
                'transporter' => 'Transport company',
            ],
        ],
        'helper' => [
            'slug' => 'Immutable. Used in URLs and database name.',
            'type' => 'Determines the panel after login (Stable → /app, Transport → /transport) and which plans are available. Immutable after creation.',
            'plan' => 'Plan list filtered by selected tenant type.',
        ],
    ],

    'notify' => [
        'created_stable' => 'Stable created',
        'created_transporter' => 'Transport company created',
        'created_body' => 'Database :db has been initialized.',
        'create_failed' => 'Tenant creation failed',
    ],

    'table' => [
        'column' => [
            'type' => 'Type',
            'country' => 'Country',
            'plan' => 'Plan',
            'db_name' => 'Database',
            'created_at' => 'Created',
        ],
        'filter' => [
            'type' => 'Tenant type',
        ],
    ],

    'action' => [
        'suspend' => [
            'label' => 'Suspend',
            'notification_title' => 'Stable suspended',
        ],
        'reactivate' => [
            'label' => 'Reactivate',
            'notification_title' => 'Stable reactivated',
        ],
        'soft_delete' => [
            'label' => 'Soft delete',
        ],
        'login_as_owner' => [
            'label' => 'Sign in as stable',
            'reason_label' => 'Impersonation reason (GDPR audit)',
            'reason_helper' => 'Required. The session is recorded in impersonation_sessions + audit_log_master.',
            'submit' => 'Start impersonation',
            'no_user_title' => 'No active user for this stable',
            'no_user_body' => 'Add a team member or invite the owner first.',
        ],
        'seed_demo' => [
            'label' => 'Seed demo data',
            'modal_heading' => 'Seed demo data into :name?',
            'modal_description' => 'Adds 14 horses, 6 clients, 12 boxes, calendar, invoices, and the rest of the demo set. Operates on the tenant database.',
            'fresh_label' => 'Wipe existing data (DROP all tables)',
            'fresh_helper' => 'WARNING: deletes all current stable data before seeding.',
            'success_title' => 'Demo data seeded',
            'success_body' => 'Stable :name now has the full demo set.',
            'failure_title' => 'Failed to seed demo',
        ],
        'destroy' => [
            'label' => 'Drop database',
            'modal_heading' => 'Permanently delete stable',
            'modal_description' => 'This operation CANNOT be undone. Database :db and MySQL account :user will be physically deleted.',
            'confirm_slug_label' => 'Type the stable slug to confirm',
            'slug_mismatch' => "Slug doesn't match.",
            'success_title' => 'Stable permanently deleted',
        ],
    ],
];
