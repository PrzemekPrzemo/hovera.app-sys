<?php

declare(strict_types=1);

return [
    'navigation' => 'Specjaliści zewnętrzni',

    'model' => [
        'singular' => 'specjalista',
        'plural' => 'Specjaliści zewnętrzni',
    ],

    'form' => [
        'section' => [
            'identity' => 'Dane tożsamości',
            'status' => 'Status konta',
        ],
        'label' => [
            'email' => 'E-mail',
            'display_name' => 'Imię i nazwisko',
            'specialty' => 'Specjalność',
            'phone' => 'Telefon',
            'setup_status' => 'Aktywacja konta',
            'verified_at' => 'Zweryfikowany',
        ],
    ],

    'status' => [
        'setup_complete' => 'Hasło ustawione, e-mail potwierdzony',
        'setup_pending' => 'Oczekuje na ustawienie hasła',
        'not_verified' => 'Niezweryfikowany',
    ],

    'table' => [
        'email' => 'E-mail',
        'display_name' => 'Imię i nazwisko',
        'specialty' => 'Specjalność',
        'setup' => 'Hasło',
        'verified' => 'Zweryf.',
        'created_at' => 'Dodany',
    ],

    'filter' => [
        'not_verified' => 'Niezweryfikowani',
        'setup_pending' => 'Bez hasła',
    ],

    'action' => [
        'verify' => [
            'label' => 'Zweryfikuj',
            'modal_heading' => 'Zweryfikuj specjalistę',
            'modal_description' => 'Po weryfikacji specjalista zostanie oznaczony jako „zweryfikowany" w widoku wątków komunikacyjnych. Sprawdź wcześniej PWZ / licencję / referencje.',
            'notify_title' => 'Specjalista zweryfikowany',
            'notify_body' => ':email otrzymał oznaczenie „verified".',
        ],
        'unverify' => [
            'label' => 'Cofnij weryfikację',
            'modal_heading' => 'Cofnij weryfikację specjalisty',
            'modal_description' => 'Cofnięcie weryfikacji usunie oznaczenie „verified" w widokach komunikacji. Użyj gdy licencja wygasła, dane okazały się nieprawdziwe lub na życzenie specjalisty.',
            'reason' => 'Powód (audit log)',
            'notify_title' => 'Weryfikacja cofnięta',
            'notify_body' => ':email nie jest już oznaczony jako zweryfikowany.',
        ],
    ],
];
