<?php

declare(strict_types=1);

return [
    'navigation' => 'SMTP (emails)',
    'title' => 'SMTP configuration — outbound email',

    'form' => [
        'section' => [
            'diagnostics' => '🔬 Diagnostics — active mailer',
            'diagnostics_description' => 'Verify SMTP config is actually in effect. If "active mailer" shows log/array — emails land in storage/logs/laravel.log instead of going out, despite a saved config.',
            'default' => 'Default SMTP mailer (master admin, password reset, notifications)',
            'default_description' => 'Used for: password reset links, master admin notifications, system alerts, tenant emails (e.g. transport company verification). Classic SMTP — Gmail, Postmark, your own mail server.',
            'mailgun' => 'Mailgun API (alternative to SMTP, EU region)',
            'mailgun_description' => 'Mailgun API — faster and more reliable than SMTP, especially at higher volumes. When `secret` is set, Mailgun WINS over the SMTP config above. Requires a verified domain in the Mailgun panel (Sending → Domain settings → DNS — SPF + DKIM TXT records).',
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
            'mailgun_domain' => 'Mailgun domain (verified)',
            'mailgun_secret' => 'Mailgun API key',
            'mailgun_endpoint' => 'Mailgun region',
            'mailgun_from_address' => 'Mailgun "From" — sender address',
            'mailgun_from_name' => 'Mailgun "From" — sender name',
            'reply_to_address' => 'Reply-To — address where replies should land',
            'reply_to_name' => 'Reply-To — name',
        ],
        'helper' => [
            'host' => 'E.g. smtp.gmail.com, smtp.sendgrid.net, smtp-relay.brevo.com',
            'password_leave_blank' => 'Leave empty to keep current value. Entering a new one overrides the previous.',
            'mailgun_domain' => 'Domain verified in the Mailgun panel, e.g. `hovera.pl` or subdomain `mg.hovera.pl`.',
            'mailgun_endpoint' => 'EU (api.eu.mailgun.net) for domains registered in the EU region. US only when the Mailgun account is US-based.',
            'mailgun_from_address' => 'MUST be on the verified Mailgun domain (e.g. noreply@hovera.pl when verified = hovera.pl). Otherwise Mailgun returns 401 Forbidden. Empty = fallback to "From email" from the SMTP section above.',
            'reply_to_address' => 'Inbox where replies land when a customer clicks "Reply" in the email. Works globally for SMTP and Mailgun. Typically `contact@hovera.pl` or `support@hovera.pl`.',
            'test_email' => 'Defaults to your master admin email. Verifies SMTP actually sends.',
            'skip_tls_verify' => 'Enable ONLY if you see "peer certificate CN did not match expected CN" — typical when shared hosting (lh.pl, home.pl, nazwa.pl) serves a wildcard cert on a different domain. Disables TLS cert verification — downgrades MITM protection. Acceptable for transactional mail, do NOT use for public mailers.',
        ],
        'encryption' => [
            'none' => 'No encryption (not recommended)',
        ],
        'status' => [
            'configured' => '✓ Configured (overrides .env)',
            'using_env' => '⚠ Using .env values (not configured in UI)',
            'mailgun_active' => '✓ Mailgun active — all emails go through Mailgun API',
            'mailgun_inactive' => '⚠ Mailgun not configured — system uses SMTP / .env',
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
        'mailgun_from_mismatch_warning' => '⚠ From "@:from_domain" does NOT match the verified Mailgun domain ":verified_domain". Mailgun will REJECT the email with 401 Forbidden. Fix: enter a "Mailgun From email" on the verified domain (e.g. noreply@:verified_domain) and save.',
        'mailgun_401_from_mismatch' => '✗ From "@:from_domain" does not match the verified Mailgun domain ":verified_domain". This is the MOST COMMON cause of 401 — sender address must be on the verified domain.',
        'mailgun_401_hint' => 'Other 401 Forbidden causes: (a) wrong region — in the Mailgun panel the domain is US, but you picked the EU endpoint (or vice versa), (b) trailing space in the copied API key, (c) using a sandbox API key on a production account (or vice versa).',
    ],

    'test_email' => [
        'subject' => 'Hovera SMTP test — it works!',
        'body' => 'This is a test email from the SMTP configuration in master admin /admin/smtp-settings. '
            .'If you can read this, SMTP is working. You can safely use this mailer for password reset, '
            .'tenant notifications, and emails from the transport module.',
    ],
];
