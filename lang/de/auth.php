<?php

declare(strict_types=1);

return [
    // Default Laravel keys
    'failed' => 'Diese Anmeldedaten stimmen nicht mit unseren Aufzeichnungen überein.',
    'password' => 'Das angegebene Passwort ist nicht korrekt.',
    'throttle' => 'Zu viele Anmeldeversuche. Bitte versuchen Sie es in :seconds Sekunden erneut.',

    // Hovera-specific
    'login' => [
        'title' => 'Anmelden — hovera',
        'heading' => 'Anmelden',
        'email' => 'E-Mail',
        'password' => 'Passwort',
        'remember' => 'Angemeldet bleiben',
        'submit' => 'Anmelden',
        'forgot_password' => 'Passwort vergessen?',
        'no_account' => 'Noch kein Konto?',
        'register' => 'Registrieren',
    ],

    'logout' => 'Abmelden',

    'two_factor' => [
        'setup_title' => '2FA-Einrichtung — hovera',
        'setup_heading' => 'Zwei-Faktor-Authentifizierung (2FA) aktivieren',
        'setup_intro' => 'Scannen Sie den QR-Code mit einer Authenticator-App (Google Authenticator, Authy, 1Password) und geben Sie den generierten sechsstelligen Code zur Bestätigung ein.',
        'manual_entry' => 'Oder geben Sie das Secret manuell ein:',
        'code_label' => '2FA-Code',
        'confirm' => 'Bestätigen und aktivieren',
        'challenge_title' => '2FA-Verifizierung — hovera',
        'challenge_heading' => '2FA-Code eingeben',
        'challenge_intro' => 'Geben Sie den sechsstelligen Code aus Ihrer Authenticator-App ein oder verwenden Sie einen Ihrer einmaligen Wiederherstellungscodes.',
        'remember_device' => 'Dieses Gerät 14 Tage merken',
        'submit_challenge' => 'Anmelden',
        'invalid_code' => 'Ungültiger Code.',
        'recovery_codes_title' => 'Wiederherstellungscodes — hovera',
        'recovery_codes_heading' => 'Ihre Wiederherstellungscodes',
        'recovery_codes_intro' => 'Bewahren Sie diese Codes an einem sicheren Ort auf. Jeder Code funktioniert nur einmal — verwenden Sie sie, wenn Sie den Zugriff auf Ihre Authenticator-App verlieren.',
        'recovery_codes_continue' => 'Codes gespeichert, weiter',
    ],

    'password_reset' => [
        'request_title' => 'Passwort zurücksetzen',
        'email_sent' => 'Wir haben einen Link zum Zurücksetzen des Passworts an Ihre E-Mail-Adresse gesendet.',
        'reset_title' => 'Neues Passwort festlegen',
        'reset_button' => 'Passwort zurücksetzen',
    ],

    'tenant_select' => [
        'title' => 'Konto wählen — Hovera',
        'heading' => 'Konto wählen',
        'intro' => 'Ihr Konto hat Zugriff auf :count Tenants (Reitställe / Transportunternehmen). Wählen Sie aus, in welchen Sie sich anmelden möchten.',
        'role_label' => ':slug · Rolle: :role',
        'submit' => 'Zum Panel',
        'no_access' => 'Kein Zugriff auf das gewählte Konto.',
        'type_stable' => 'Reitstall',
        'type_transporter' => 'Transportunternehmen',
        'status_provisioning' => 'Wartet auf Verifizierung',
    ],

    'no_tenants' => [
        'title' => 'Keine Konten verfügbar — Hovera',
        'heading' => 'Keine Konten verfügbar',
        'intro' => 'Ihr Konto ist noch keinem Reitstall oder Transportunternehmen zugeordnet, oder Ihr Zugriff wurde entzogen. Wenden Sie sich an den Administrator, um Zugriff zu erhalten.',
        'logout' => 'Abmelden',
    ],

    'invitation_accept' => [
        'title' => 'Konto aktivieren — Hovera',
        'heading' => 'Passwort festlegen',
        'intro_with_tenant' => 'Sie treten dem Reitstall <strong>:tenant</strong> bei.',
        'intro_account' => 'Konto: <strong>:email</strong>.',
        'intro_pwd' => 'Wählen Sie ein Passwort (mind. 12 Zeichen), um Ihr Konto zu aktivieren.',
        'password' => 'Neues Passwort',
        'password_confirmation' => 'Passwort wiederholen',
        'submit' => 'Konto aktivieren und anmelden',
    ],
];
