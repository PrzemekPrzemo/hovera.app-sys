<?php

declare(strict_types=1);

return [
    'section' => [
        'title' => 'PayU — Auto-Link auf Angeboten',
        'description' => 'Automatisches Generieren eines PayU-Links (BLIK / Überweisung / Karte / '
            .'Apple Pay / Google Pay) für jedes neue Transportangebot. Der Kunde zahlt direkt auf Ihr PayU-Konto.',
        'disclaimer' => 'PayU ist IHR Konto, IHR Vertrag mit PayU.pl S.A., IHRE Rechnungen. '
            .'Hovera leitet den Kunden nur technisch zu Ihrem Checkout weiter — alle Gelder '
            .'landen direkt auf Ihrem PayU-Konto (Hovera ist kein Zahlungsvermittler für '
            .'Transporte — siehe docs/TRANSPORT.md §12 und §16).',
    ],

    'form' => [
        'label' => [
            'autopay_enabled' => 'PayU-Link für neue Angebote automatisch generieren',
        ],
        'helper' => [
            'autopay_enabled' => 'Wenn aktiviert, wird beim Erstellen eines Angebots in PLN '
                .'automatisch eine PayU-Bestellung registriert und der Link als payment_url '
                .'gespeichert. Der Kunde sieht eine "Mit PayU bezahlen"-Schaltfläche auf '
                .'der öffentlichen Angebotsseite.',
            'credentials_pointer' => 'Konfigurieren Sie pos_id / oauth_client_id / oauth_client_secret '
                .'/ md5_key in der Seite "Zahlungseinstellungen" (/app/payment-settings).',
        ],
    ],

    'notify' => [
        'autopay_failed' => 'Der PayU-Link konnte nicht generiert werden',
    ],

    'return' => [
        'paid' => 'Die Zahlung für Angebot {number} ist eingegangen — vielen Dank!',
        'pending' => 'Die Zahlung für Angebot {number} wird überprüft. Bitte aktualisieren '
            .'Sie die Seite in einem Moment.',
        'unknown' => 'Angebot nicht gefunden. Bitte kontaktieren Sie den Transporteur.',
    ],
];
