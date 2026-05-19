<?php

declare(strict_types=1);

return [
    'payment_method_label' => 'Stripe (Karte / BLIK / Przelewy24)',

    'section' => [
        'title' => 'Stripe Connect Express (Online-Zahlungen)',
        'description' => 'Aktivierung mit einem Klick — Ihr eigenes Stripe-Express-Konto, Geld geht direkt an Sie. Pay-online für jedes Angebot automatisch.',
        'disclaimer' => 'Stripe Connect Express: IHR Stripe-Konto, IHR Vertrag mit Stripe (KYC bei Stripe). Hovera ermöglicht nur technisch den Checkout — Geld geht direkt an Sie. Hovera kann (standardmäßig nicht) eine Transaktionsprovision erheben — siehe §15 der Marketplace-AGB.',
    ],

    'form' => [
        'label' => [
            'status' => 'Integrationsstatus',
        ],
    ],

    'status' => [
        'none' => 'Nicht verbunden',
        'pending' => 'Verifizierung bei Stripe läuft',
        'enabled' => 'Aktiv — Sie können Zahlungen annehmen',
        'restricted' => 'Eingeschränkt — Daten bei Stripe ergänzen',
        'rejected' => 'Abgelehnt — kontaktieren Sie den Stripe-Support',
    ],

    'action' => [
        'connect' => 'Stripe-Konto verbinden',
        'refresh_status' => 'Status prüfen',
        'open_dashboard' => 'Stripe-Dashboard öffnen',
        'admin_sync' => 'Stripe-Status synchronisieren',
    ],

    'notify' => [
        'onboard_failed' => 'Stripe-Onboarding konnte nicht gestartet werden.',
        'status_sync_failed' => 'Stripe-Status konnte nicht synchronisiert werden.',
        'dashboard_failed' => 'Stripe-Dashboard-Link konnte nicht generiert werden.',
        'refreshed' => 'Stripe-Status aktualisiert.',
        'status_none' => 'Kein Stripe-Konto — klicken Sie auf „Stripe-Konto verbinden".',
        'status_pending' => 'KYC läuft — Stripe prüft Firmendaten. Versuchen Sie es in Kürze erneut.',
        'status_enabled' => 'Stripe-Konto aktiv — Sie können Angebote mit Online-Zahlung ausstellen.',
        'status_restricted' => 'Stripe hat das Konto eingeschränkt — prüfen Sie das Dashboard und ergänzen Sie fehlende Daten.',
        'status_rejected' => 'Stripe hat das Konto abgelehnt — Kontakt mit Stripe-Support erforderlich.',
    ],
];
