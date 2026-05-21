<?php

declare(strict_types=1);

return [
    'access' => [
        'not_owner' => 'Nie jesteś właścicielem konia, którego dotyczy ten request.',
    ],

    'field' => [
        'name' => 'Imię konia',
        'passport_number' => 'Numer paszportu',
        'microchip' => 'Mikrochip',
    ],

    'status' => [
        'pending' => 'Oczekuje na decyzję',
        'accepted' => 'Zatwierdzona',
        'rejected' => 'Odrzucona (wartość cofnięta)',
    ],
];
