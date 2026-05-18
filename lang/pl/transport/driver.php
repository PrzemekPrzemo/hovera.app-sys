<?php

declare(strict_types=1);

return [
    'section' => [
        'personal' => 'Dane osobowe',
        'contact' => 'Kontakt',
        'license' => 'Prawo jazdy',
        'qualifications' => 'Uprawnienia dodatkowe',
        'qualifications_description' => 'Świadectwo kwalifikacji do transportu zwierząt (ustawa). ADR — gdy planujesz materiały niebezpieczne.',
        'other' => 'Pozostałe',
    ],

    'form' => [
        'label' => [
            'first_name' => 'Imię',
            'last_name' => 'Nazwisko',
            'date_of_birth' => 'Data urodzenia',
            'email' => 'Email',
            'phone' => 'Telefon',
            'license_number' => 'Numer prawa jazdy',
            'license_categories' => 'Kategorie',
            'license_expires_at' => 'Ważne do',
            'has_animal_transport_cert' => 'Świadectwo transportu zwierząt',
            'animal_transport_cert_expires_at' => 'Ważne do',
            'has_adr' => 'ADR',
            'adr_expires_at' => 'Ważne do',
            'hire_date' => 'Data zatrudnienia',
            'is_active' => 'Aktywny',
            'sort_order' => 'Kolejność',
            'notes' => 'Notatki',
        ],
        'helper' => [
            'email' => 'Adres używany do powiadomień o przydzielonych zleceniach.',
        ],
    ],

    'table' => [
        'column' => [
            'full_name' => 'Kierowca',
            'phone' => 'Telefon',
            'email' => 'Email',
            'license_expires_at' => 'Prawo jazdy do',
            'has_animal_transport_cert' => 'Transp. zwierząt',
            'is_active' => 'Aktywny',
        ],
    ],

    'filter' => [
        'license_expiring_soon' => 'Prawo jazdy kończy się w ciągu 30 dni',
    ],
];
