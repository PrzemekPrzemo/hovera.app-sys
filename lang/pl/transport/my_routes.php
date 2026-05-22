<?php

declare(strict_types=1);

return [
    'navigation' => 'Moje trasy',
    'model' => 'trasa',
    'model_plural' => 'moje trasy',

    'column' => [
        'date' => 'Termin',
        'pickup' => 'Odbiór',
        'dropoff' => 'Dostarczenie',
        'customer' => 'Klient',
        'phone' => 'Telefon',
        'status' => 'Status',
    ],

    'empty' => [
        'heading' => 'Brak przypisanych tras',
        'description' => 'Operator przypisze Ci trasy gdy klient zaakceptuje ofertę. Notyfikacja przyjdzie mailem.',
    ],
];
