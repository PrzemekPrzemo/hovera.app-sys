<?php

declare(strict_types=1);

return [
    'navigation' => 'Rechnungen',

    'section' => [
        'header' => 'Kopfdaten',
        'parties' => 'Parteien',
        'amounts' => 'Beträge',
        'dates' => 'Daten',
        'route' => 'Strecke',
        'notes' => 'Notizen',
        'correction' => 'Rechnungskorrektur',
        'correction_help' => 'Wählen Sie die ursprüngliche Rechnung, die diese KOR korrigiert.',
    ],

    'form' => [
        'label' => [
            'seller' => 'Verkäufer',
            'buyer' => 'Käufer',
            'net_total' => 'Netto',
            'vat_total' => 'MwSt.',
            'gross_total' => 'Brutto',
            'corrects_invoice' => 'Korrigierte Rechnung',
        ],
        'helper' => [
            'kind' => 'Wählen Sie „Korrektur" wenn diese Rechnung eine zuvor ausgestellte korrigiert.',
            'corrects_invoice' => 'Nummer der ursprünglichen Rechnung. Erscheint in XML im `<DaneFaKorygowanej>` Block.',
        ],
    ],

    'table' => [
        'column' => [
            'number' => 'Nummer',
            'kind' => 'Art',
            'buyer' => 'Käufer',
            'issued_at' => 'Ausgestellt',
            'due_at' => 'Fällig',
            'total' => 'Brutto',
            'status' => 'Status',
        ],
    ],

    'action' => [
        'download_pdf' => 'PDF herunterladen',
        'send_email' => 'Per E-Mail senden',
        'mark_paid' => 'Als bezahlt markieren',
    ],

    'notify' => [
        'sent' => 'Rechnung versendet',
        'sent_body' => 'Rechnung :number an :email mit PDF im Anhang gesendet.',
        'no_buyer_email' => 'Käufer hat keine E-Mail — PDF herunterladen und manuell senden.',
        'email_failed' => 'Versand fehlgeschlagen',
        'marked_paid' => 'Rechnung als bezahlt markiert',
    ],
];
