<?php

declare(strict_types=1);

return [
    'navigation' => 'SMTP (emails)',
    'title' => 'SMTP configuration — outbound email',

    'form' => [
        'section' => [
            'default' => 'Default mailer (master admin, password reset, notifications)',
            'default_description' => 'Used for: password reset links, master admin notifications, system alerts, tenant emails (e.g. transport company verification).',
            'transport' => 'Transport mailer (offers + dispatcher to drivers)',
            'transport_description' => 'Dedicated mailer for emails sent by the transport module — offers to customers, dispatcher to drivers, review requests. Separate credentials + separate "From" to keep domain reputation independent from the system mailer.',
            'test' => '🧪 Test send',
            'test_description' => 'Sends a test email using CURRENTLY SAVED settings (after Save). If you change config, Save first, then Test.',
        ],
        'label' => [
            'host' => 'SMTP host',
            'port' => 'Port',
            'username' => 'Username (login)',
            'password' => 'Password',
            'encryption' => 'Encryption',
            'from_address' => 'From email',
            'from_name' => 'From name',
            'status' => 'Status',
            'test_email' => 'Send test email to',
        ],
        'helper' => [
            'host' => 'E.g. smtp.gmail.com, smtp.sendgrid.net, smtp-relay.brevo.com',
            'password_leave_blank' => 'Leave empty to keep current password. Entering a new one overrides the previous.',
            'test_email' => 'Defaults to your master admin email. Verifies SMTP actually sends.',
        ],
        'encryption' => [
            'none' => 'No encryption (not recommended)',
        ],
        'status' => [
            'configured' => '✓ Configured (overrides .env)',
            'using_env' => '⚠ Using .env values (not configured in UI)',
        ],
    ],

    'action' => [
        'save_button' => 'Save SMTP configuration',
        'saved' => 'SMTP saved',
        'saved_body' => 'Override .env is now active. Next request will use the new settings.',
        'send_test_button' => 'Send test',
        'test_sent' => 'Test sent to :email — check the inbox.',
        'test_failed' => 'Test send failed — check the config',
        'test_invalid_email' => 'Enter a valid email address for the test',
    ],

    'test_email' => [
        'subject' => 'Hovera SMTP test — it works!',
        'body' => 'This is a test email from the SMTP configuration in master admin /admin/smtp-settings. '
            .'If you can read this, SMTP is working. You can safely use this mailer for password reset, '
            .'tenant notifications, and emails from the transport module.',
    ],
];
