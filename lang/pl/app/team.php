<?php

declare(strict_types=1);

return [
    'form' => [
        'label' => [
            'role' => 'Rola',
        ],
    ],

    'table' => [
        'column' => [
            'email' => 'Email',
            'name' => 'Imię i nazwisko',
            'role' => 'Rola',
            'joined_at' => 'Dołączył',
            'revoked_at' => 'Cofnięto',
        ],
    ],

    'action' => [
        'add' => [
            'label' => 'Dodaj pracownika',
        ],
        'send_password_reset' => [
            'label' => 'Wyślij link resetu hasła',
            'modal_description' => 'Wyślemy email z linkiem do zresetowania hasła na adres :email. Link wygasa po 60 minutach.',
            'success_title' => 'Link resetu hasła wysłany',
            'success_body' => 'Email wysłany na :email. Pracownik powinien sprawdzić skrzynkę (też SPAM).',
            'failure_title' => 'Nie udało się wysłać linku',
            'failure_no_email' => 'Pracownik nie ma adresu e-mail w profilu.',
        ],
    ],
];
