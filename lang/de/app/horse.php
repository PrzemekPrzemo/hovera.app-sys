<?php

declare(strict_types=1);

return [
    'sex' => [
        'mare' => 'Stute',
        'gelding' => 'Wallach',
        'stallion' => 'Hengst',
        'breeding_stallion' => 'Deckhengst',
    ],

    'form' => [
        'section' => [
            'identification' => 'Identifikation',
            'characteristics' => 'Merkmale',
            'boarding' => 'Pension — abgerechnete Leistungen',
            'boarding_description' => 'Markieren Sie, welche Positionen der Preisliste auf dieses Pferd zutreffen. Der Kunde sieht sie im Portal mit dem geschätzten Monatsbetrag.',
            'notes' => 'Notizen',
            'sport' => 'Sport (LiveJumping)',
            'sport_help' => 'Fügen Sie die URL des Pferdeprofils von LiveJumping.com ein — wir zeigen Palmarès und kommende Starts an.',
        ],
        'label' => [
            'name' => 'Name',
            'owner' => 'Besitzer',
            'owner_placeholder' => '— Reitstall —',
            'box' => 'Box',
            'box_placeholder' => '— ohne Zuweisung —',
            'microchip' => 'Mikrochip',
            'passport_number' => 'Passnummer',
            'ueln' => 'UELN',
            'sex' => 'Geschlecht',
            'breed' => 'Rasse',
            'color' => 'Farbe',
            'birth_date' => 'Geburtsdatum',
            'boarding_services' => 'Leistungen aus der Preisliste',
            'livejumping_profile_url' => 'URL des LiveJumping-Profils',
            'livejumping_palmares' => 'Palmarès',
        ],
        'helper' => [
            'box' => 'Eine Boxenänderung wird in „Boxen → Zuweisungshistorie" protokolliert.',
            'ueln' => 'Universal Equine Life Number',
            'boarding_services' => 'Die Preisliste konfigurieren Sie unter „Reitstall → Pensionspreise". Preis-Override pro Pferd (z. B. Rabatt) setzen Sie dort manuell nach Anlage des Eintrags.',
            'livejumping_profile_url' => 'Kopieren Sie die URL der Profilseite von livejumping.com — z. B. https://livejumping.com/horse/12345/romeo',
            'livejumping_no_profile' => 'Fügen Sie oben eine LJ-Profil-URL ein, um das Palmarès zu sehen.',
            'livejumping_fetch_failed' => 'Daten konnten nicht von LiveJumping abgerufen werden (URL prüfen oder später erneut versuchen).',
        ],
        'stats' => [
            'starts' => 'Starts',
            'wins' => 'Siege',
            'placings' => 'Top-Platzierungen',
            'ranking_points' => 'Ranglistenpunkte',
            'recent_results' => 'Aktuelle Ergebnisse',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Name',
            'breed' => 'Rasse',
            'sex' => 'Geschlecht',
            'color' => 'Farbe',
            'birth_date' => 'Geb.',
            'owner' => 'Besitzer',
            'owner_placeholder' => '— Reitstall —',
            'created_at' => 'Hinzugefügt',
        ],
    ],
];
