<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'numbering' => 'Rechnungsnummerierung',
            'numbering_description' => 'Platzhalter: {seq}, {seq:NN} (z. B. {seq:4} → 0001), {YYYY}, {YY}, {MM}, {M}, {DD}, {prefix}.',
            'seller' => 'Verkäuferdaten (Snapshot auf Rechnungen)',
            'seller_description' => 'Diese Daten werden auf jeder neuen Rechnung beim Anlegen gespeichert. Eine Änderung der Reitstall-Daten ändert bereits ausgestellte Rechnungen nicht.',
        ],
        'label' => [
            'template_fv' => 'Vorlage Rechnung',
            'template_pro' => 'Vorlage Proforma',
            'template_kor' => 'Vorlage Korrektur',
            'prefix' => 'Präfix (Platzhalter {prefix})',
            'prefix_placeholder' => 'z. B. STW',
            'reset_interval' => 'Nummerierungs-Reset',
            'default_due_days' => 'Standard-Zahlungsfrist (Tage)',
            'seller_name' => 'Name des Verkäufers',
            'seller_nip' => 'USt-IdNr. des Verkäufers',
            'seller_address' => 'Adresse',
            'seller_postal_code' => 'PLZ',
            'seller_city' => 'Stadt',
        ],
    ],

    'action' => [
        'saved' => 'Rechnungseinstellungen gespeichert',
    ],

    'reset_options' => [
        'yearly' => 'Jährlich (Start bei 1 im neuen Jahr)',
        'monthly' => 'Monatlich (Start bei 1 zu jedem Monatsbeginn)',
        'never' => 'Nie (fortlaufende Nummerierung)',
    ],
];
