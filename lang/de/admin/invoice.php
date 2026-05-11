<?php

declare(strict_types=1);

return [
    'navigation' => 'SaaS-Rechnungen',
    'model' => 'SaaS-Rechnung',
    'model_plural' => 'SaaS-Rechnungen',

    'kind' => [
        'regular' => 'Regulär (Rechnung)',
        'proforma' => 'Proforma',
        'correction' => 'Korrektur',
    ],

    'form' => [
        'section' => [
            'basics' => 'Stammdaten',
            'amounts' => 'Beträge',
            'dates' => 'Daten',
        ],
        'label' => [
            'tenant' => 'Reitstall (Käufer)',
            'number' => 'Rechnungsnummer',
            'kind' => 'Typ',
            'subtotal' => 'Netto (Groschen)',
            'vat_rate' => 'MwSt.-Satz (%)',
        ],
    ],

    'table' => [
        'column' => [
            'number' => 'Nummer',
            'tenant' => 'Reitstall',
            'issued_at' => 'Ausgestellt',
            'total' => 'Bruttosumme',
            'status' => 'Status',
            'ksef_status' => 'KSeF',
        ],
    ],

    'action' => [
        'issue_manual' => 'Rechnung manuell ausstellen',
        'send_p24_link' => 'P24-Link senden',
        'p24_link_generated' => 'Przelewy24-Link generiert',
        'p24_link_failed' => 'P24-Link konnte nicht generiert werden',
        'send_to_ksef' => 'An KSeF senden',
        'ksef_sent' => 'An KSeF gesendet',
        'ksef_failed' => 'KSeF-Versand fehlgeschlagen',
        'ksef_reference' => 'KSeF-Referenznummer',
        'download_pdf' => 'PDF herunterladen',
        'pdf_stub_title' => 'PDF-Generierung pausiert',
        'pdf_stub_body' => 'Die vollständige PDF-Generierung erfordert dompdf/snappy — wird in einem Follow-up-PR ergänzt.',
        'resend_email' => 'E-Mail erneut senden',
    ],

    'p24_return' => [
        'paid' => 'Die Zahlung für Rechnung :number wurde bestätigt.',
        'pending' => 'Vielen Dank! Die Zahlung für Rechnung :number wird verifiziert — dies dauert in der Regel einige Minuten.',
        'unknown' => 'Rechnung nicht erkannt — prüfen Sie die Bestätigungs-E-Mail.',
    ],
];
