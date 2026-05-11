<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'invoice_data' => 'Rechnungsdaten',
            'buyer' => 'Käufer',
            'seller' => 'Verkäufer (Snapshot)',
            'dates' => 'Daten',
            'items' => 'Positionen',
            'notes' => 'Notizen',
        ],
        'label' => [
            'kind' => 'Art',
            'number' => 'Nummer',
            'number_placeholder' => '— wird bei Ausstellung vergeben —',
            'status' => 'Status',
            'client' => 'Kunde',
            'buyer_name' => 'Name / Vor- und Nachname',
            'buyer_nip' => 'USt-IdNr. (optional für Privatpersonen)',
            'buyer_address' => 'Adresse',
            'buyer_postal_code' => 'PLZ',
            'buyer_city' => 'Stadt',
            'buyer_country' => 'Land',
            'seller_name' => 'Name',
            'seller_nip' => 'USt-IdNr.',
            'seller_address' => 'Adresse',
            'seller_postal_code' => 'PLZ',
            'seller_city' => 'Stadt',
            'seller_country' => 'Land',
            'issued_at' => 'Ausgestellt',
            'sale_date' => 'Verkaufsdatum',
            'due_at' => 'Fälligkeitsdatum',
            'item_name' => 'Bezeichnung',
            'item_quantity' => 'Menge',
            'item_unit' => 'Einh.',
            'item_unit_price' => 'Einheitspreis netto',
            'item_vat' => 'MwSt.',
            'notes_label' => 'Anmerkungen',
        ],
    ],

    'table' => [
        'column' => [
            'number' => 'Nummer',
            'kind' => 'Art',
            'issued_at' => 'Ausgestellt',
            'client' => 'Käufer',
            'total' => 'Brutto',
            'status' => 'Status',
            'due_at' => 'Fällig',
        ],
        'filter' => [
            'overdue' => 'Überfällig',
        ],
    ],

    'action' => [
        'issue' => [
            'label' => 'Ausstellen',
            'success' => 'Rechnung ausgestellt',
            'failure_title' => 'Rechnung kann nicht ausgestellt werden',
        ],
        'correct' => [
            'label' => 'Korrektur',
            'success_title' => 'Korrektur erstellt',
            'success_body' => 'Öffnen Sie den Entwurf :id und bearbeiten Sie die Positionen.',
            'failure_title' => 'Fehler',
        ],
        'ksef' => [
            'label' => 'An KSeF senden',
            'modal_description' => 'Die Rechnung wird mit dem Zertifikat des Reitstalls signiert und an KSeF gesendet.',
            'auth_success_title' => 'KSeF: Authentifizierung erfolgreich',
            'auth_success_body' => 'Versand des Rechnungsinhalts wird vorbereitet (PR 4b).',
            'failure_title' => 'KSeF: Fehler',
        ],
        'email' => [
            'label' => 'Per E-Mail senden',
            'modal_description' => 'Wir senden einen Link zur Rechnung an die E-Mail des Kunden. Der Link ist bis zu 90 Tage gültig (bzw. 14 Tage nach Zahlungsfrist).',
            'no_email' => 'Keine E-Mail-Adresse des Kunden',
            'success' => 'Rechnung per E-Mail an den Kunden gesendet',
        ],
    ],
];
