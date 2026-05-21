<?php

declare(strict_types=1);

return [
    'title' => 'Historia działań na koniu',
    'breadcrumb' => 'Oś czasu',
    'summary' => 'Pokazuję :count wpisów (ostatnie najpierw, do 200).',

    'filter' => [
        'heading' => 'Filtry',
        'kinds' => 'Kategorie',
        'from' => 'Od dnia',
        'to' => 'Do dnia',
    ],

    'action' => [
        'apply' => 'Zastosuj',
        'reset' => 'Wyczyść filtry',
    ],

    'empty' => [
        'heading' => 'Brak wpisów',
        'description' => 'Stajnia nie zarejestrowała jeszcze żadnych działań na tym koniu w wybranym zakresie.',
    ],

    'kind' => [
        'health' => 'Zdrowie',
        'box' => 'Boks',
        'weight' => 'Waga',
        'activity' => 'Aktywność',
        'photo' => 'Zdjęcie',
        'document' => 'Dokument',
    ],

    'subkind' => [
        'health' => [
            'vet_visit' => 'wizyta wet.',
            'vaccination' => 'szczepienie',
            'deworming' => 'odrobaczanie',
            'dentist' => 'dentysta',
            'farrier' => 'kowal',
            'check_up' => 'przegląd',
            'medication' => 'leczenie',
            'other' => 'inne',
        ],
        'box' => [
            'assigned' => 'wprowadzony',
            'vacated' => 'opuścił',
        ],
        'weight' => [
            'measured' => 'pomiar wagi',
        ],
        'activity' => [
            'feeding' => 'karmienie',
            'grooming' => 'pielęgnacja',
            'turnout' => 'padok',
            'exercise' => 'praca / lonża',
            'box_cleaning' => 'sprzątanie boksu',
            'transport_event' => 'wyjazd na zawody',
            'other' => 'inne',
        ],
        'photo' => [
            'added' => 'nowe zdjęcie',
        ],
        'document' => [
            'passport' => 'paszport',
            'contract' => 'kontrakt',
            'insurance' => 'ubezpieczenie',
            'vaccine_book' => 'książeczka szczepień',
            'ownership_proof' => 'dowód własności',
            'competition_licence' => 'licencja sportowa',
            'vet_certificate' => 'świadectwo wet.',
            'other' => 'inny dokument',
        ],
    ],

    'actor' => [
        'stable' => 'Stajnia',
        'owner' => 'Właściciel',
        'system' => 'System',
    ],

    'next_due_at' => 'Kolejny termin',
    'view_link' => 'Oś czasu',
];
