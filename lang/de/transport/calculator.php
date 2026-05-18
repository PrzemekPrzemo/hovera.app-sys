<?php

declare(strict_types=1);

return [
    'navigation' => 'Angebotsrechner',
    'title' => 'Transportangebot-Rechner',

    'section' => [
        'route' => 'Route',
        'options' => 'Optionen',
    ],

    'form' => [
        'label' => [
            'from_address' => 'Abholadresse',
            'to_address' => 'Lieferadresse',
            'loaded' => 'Beladen (mit Pferd)',
            'round_trip' => 'Hin- und Rückfahrt',
            'avoid_tolls' => 'Mautstraßen vermeiden',
            'avoid_ferries' => 'Fähren vermeiden',
            'profile' => 'Fahrzeugprofil',
        ],
        'placeholder' => [
            'from_address' => 'z. B. Reitstall, Hauptstr. 1, Berlin',
            'to_address' => 'z. B. München, Sportstr. 1',
        ],
        'option' => [
            'profile' => [
                'truck' => 'LKW (HGV)',
                'car' => 'PKW',
            ],
        ],
    ],

    'action' => [
        'submit' => 'Angebot berechnen',
        'calculated' => 'Angebot berechnet.',
        'failed' => 'Berechnung fehlgeschlagen',
        'save_as_quote' => 'Als Angebot speichern',
    ],

    'result' => [
        'heading' => 'Angebotsergebnis',
        'from' => 'Von',
        'to' => 'Nach',
        'distance' => 'Entfernung',
        'duration' => 'Fahrzeit',
        'rate_used' => 'Angewandter Tarif',
        'base_cost' => 'Grundpreis',
        'fuel_surcharge' => 'Kraftstoffzuschlag',
        'minimum_adjustment' => 'Anpassung an Mindestpreis',
        'net_total' => 'Netto-Gesamt',
        'vat' => 'MwSt. (:rate%)',
        'gross_total' => 'Brutto-Gesamt',
        'routing_via' => 'Route berechnet via: :provider',
    ],

    'error' => [
        'no_tenant' => 'Kein aktiver Tenant — bitte erneut anmelden.',
        'unknown' => 'Unerwarteter Fehler. Bitte erneut versuchen.',
    ],
];
