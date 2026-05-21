<?php

declare(strict_types=1);

return [
    'navigation' => 'SMTP (emails)',
    'title' => 'SMTP configuration — outbound email',

    'form' => [
        'section' => [
            'diagnostics' => '🔬 Diagnostics — active mailer',
            'diagnostics_description' => 'Verify SMTP config is actually in effect. If "active mailer" shows log/array — emails land in storage/logs/laravel.log instead of going out, despite a saved config.',
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
            'skip_tls_verify' => 'Skip TLS certificate verification (cert hostname mismatch)',
            'from_address' => 'From email',
            'from_name' => 'From name',
            'status' => 'Status',
            'test_email' => 'Send test email to',
            'effective_mailer' => 'Configuration state',
        ],
        'helper' => [
            'host' => 'E.g. smtp.gmail.com, smtp.sendgrid.net, smtp-relay.brevo.com',
            'password_leave_blank' => 'Leave empty to keep current password. Entering a new one overrides the previous.',
            'test_email' => 'Defaults to your master admin email. Verifies SMTP actually sends.',
            'skip_tls_verify' => 'Enable ONLY if you see "peer certificate CN did not match expected CN" — typical when shared hosting (lh.pl, home.pl, nazwa.pl) serves a wildcard cert on a different domain. Disables TLS cert verification — downgrades MITM protection. Acceptable for transactional mail, do NOT use for public mailers.',
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
        'test_sent_body' => 'Mailer used: :mailer. If the email does not arrive within 2 min — check spam, then logs (storage/logs/laravel.log) for SMTP errors.',
        'test_failed' => 'Test send failed — check the config',
        'test_invalid_email' => 'Enter a valid email address for the test',
    ],

    'diagnostics' => [
        'effective_mailer' => 'Active mailer',
        'env_mailer' => 'MAIL_MAILER (.env)',
        'override_active' => 'UI override',
        'override_yes' => 'active — UI overrides .env',
        'override_no' => 'inactive — using .env',
        'from' => 'From',
        'from_missing' => 'no sender address',
        'not_sending' => 'NOT sending emails!',
        'no_host' => 'no SMTP host',
        'log_mailer_warning' => 'Active mailer is ":mailer" — meaning emails do NOT go out via SMTP but land in the log file (storage/logs/laravel.log) or memory. Fix: save SMTP config below (Save) — AppServiceProvider will auto-switch the mailer to "smtp" on the next request.',
        'log_mailer_explanation' => 'SMTP config saved in the UI is NOT enough if the active mailer is log/array. In that case: 1) Fill host/port/username/password/from below, 2) Click "Save SMTP configuration", 3) Refresh this page — diagnostics should show "smtp → <host>", 4) Then click "Send test".',
    ],

    'test_email' => [
        'subject' => 'Hovera SMTP test — it works!',
        'body' => 'This is a test email from the SMTP configuration in master admin /admin/smtp-settings. '
            .'If you can read this, SMTP is working. You can safely use this mailer for password reset, '
            .'tenant notifications, and emails from the transport module.',
    ],
];
