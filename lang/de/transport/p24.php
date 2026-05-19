<?php

declare(strict_types=1);

return [
    'section' => [
        'title' => 'Przelewy24 — Auto-Link auf Angeboten',
        'description' => 'Automatisches Generieren eines P24-Links (BLIK / Überweisung / Karte) '
            .'für jedes neue Transportangebot. Der Kunde zahlt direkt auf Ihr P24-Konto.',
        'disclaimer' => 'Przelewy24 ist IHR Konto, IHR Vertrag mit DialCom24, IHRE Rechnungen. '
            .'Hovera leitet den Kunden nur technisch zu Ihrem Checkout weiter — alle Gelder '
            .'landen direkt auf Ihrem P24-Konto (Hovera ist kein Zahlungsvermittler für '
            .'Transporte — siehe docs/TRANSPORT.md §12 und §15.5).',
    ],

    'form' => [
        'label' => [
            'autopay_enabled' => 'P24-Link für neue Angebote automatisch generieren',
        ],
        'helper' => [
            'autopay_enabled' => 'Wenn aktiviert, wird beim Erstellen eines Angebots in PLN '
                .'automatisch eine P24-Sitzung registriert und der Link als payment_url '
                .'gespeichert. Der Kunde sieht eine "Mit Przelewy24 bezahlen"-Schaltfläche '
                .'auf der öffentlichen Angebotsseite.',
            'credentials_pointer' => 'Konfigurieren Sie merchant_id / pos_id / crc / api_key '
                .'in der Seite "Zahlungseinstellungen" (/app/payment-settings). Ein Formular '
                .'deckt alle P24-Integrationen ab (Buchungen, Angebote).',
        ],
    ],

    'notify' => [
        'autopay_failed' => 'Der Przelewy24-Link konnte nicht generiert werden',
    ],

    'return' => [
        'paid' => 'Die Zahlung für Angebot {number} ist eingegangen — vielen Dank!',
        'pending' => 'Die Zahlung für Angebot {number} wird überprüft. Bitte aktualisieren '
            .'Sie die Seite in einem Moment.',
        'unknown' => 'Angebot nicht gefunden. Bitte kontaktieren Sie den Transporteur.',
    ],
];
