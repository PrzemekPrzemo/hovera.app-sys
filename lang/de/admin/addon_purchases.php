<?php

declare(strict_types=1);

return [
    'navigation' => 'Add-on-Käufe',
    'model' => 'Add-on-Kauf',
    'model_plural' => 'Add-on-Käufe',

    'form' => [
        'section' => [
            'basics' => 'Grundinformationen',
            'status' => 'Status und Zahlung',
        ],
        'label' => [
            'tenant' => 'Stall (Tenant)',
            'addon' => 'Add-on (aus Katalog wählen)',
            'addon_code' => 'Add-on-Code',
            'addon_name' => 'Add-on-Name (Snapshot)',
            'currency' => 'Währung',
            'amount_cents' => 'Betrag (kleinste Einheit)',
            'status' => 'Status',
            'p24_link' => 'P24-Link (nach Generierung)',
            'p24_link_none' => '— kein Link, Aktion „P24-Link generieren" verwenden',
        ],
        'helper' => [
            'amount_cents' => 'Betrag in der kleinsten Einheit (Grosze für PLN, Cents für EUR). '
                .'Automatisch aus PlanAddon-Preisliste übernommen nach Auswahl oben.',
        ],
    ],

    'status' => [
        'pending' => 'Wartet auf Zahlung',
        'paid' => 'Bezahlt',
        'failed' => 'Zahlung fehlgeschlagen',
        'cancelled' => 'Storniert',
    ],

    'table' => [
        'column' => [
            'tenant' => 'Stall',
            'addon' => 'Add-on',
            'amount' => 'Betrag',
            'status' => 'Status',
            'paid_at' => 'Bezahlt am',
            'created_at' => 'Erstellt am',
        ],
    ],

    'action' => [
        'generate_p24_link' => 'P24-Link generieren',
    ],

    'notify' => [
        'link_generated' => 'P24-Link generiert — unten kopieren und an den Kunden senden',
        'link_failed' => 'P24-Link konnte nicht generiert werden',
    ],

    'return' => [
        'paid' => 'Add-on-Kauf „{code}" wurde verbucht — vielen Dank!',
        'pending' => 'Add-on-Kauf „{code}" wird überprüft.',
        'unknown' => 'Add-on-Kauf nicht gefunden.',
    ],
];
