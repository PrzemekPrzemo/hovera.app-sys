<?php

declare(strict_types=1);

return [
    'login' => [
        'title' => 'Panel klienta — :tenant',
        'heading' => 'Panel klienta — :tenant',
        'intro' => 'Wpisz adres e-mail, na który zostały wysyłane potwierdzenia rezerwacji. Otrzymasz link do logowania.',
        'email' => 'E-mail',
        'submit' => 'Wyślij link logowania',
        'back' => '← Wróć do strony stajni',
    ],

    'sent' => [
        'title' => 'Sprawdź skrzynkę — :tenant',
        'heading' => 'Sprawdź skrzynkę',
        'body' => 'Jeśli adres <strong>:email</strong> jest powiązany z kontem w <strong>:tenant</strong>, wysłaliśmy link do logowania.',
        'ttl' => 'Link działa przez 30 minut.',
        'back' => '← Wróć',
    ],

    'invalid' => [
        'title' => 'Link nieaktywny — :tenant',
        'heading' => 'Link nieaktywny',
        'body' => 'Ten link logowania wygasł lub został już użyty. Linki są jednorazowe i ważne 30 minut.',
        'request_new' => 'Wyślij nowy link',
    ],
];
