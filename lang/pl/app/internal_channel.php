<?php

declare(strict_types=1);

return [
    'nav' => 'Kanały zespołu',
    'model' => 'Kanał',
    'model_plural' => 'Kanały zespołu',
    'messages' => 'Wiadomości',

    'form' => [
        'name' => 'Nazwa kanału',
        'description' => 'Opis',
        'message' => 'Wiadomość',
        'message_hint' => 'Możesz wspomnieć osobę przez @nazwa (np. @anna).',
    ],

    'table' => [
        'name' => 'Kanał',
        'description' => 'Opis',
        'default' => 'Domyślny',
        'members' => 'Członkowie',
    ],

    'action' => [
        'new' => 'Nowy kanał',
        'open' => 'Otwórz',
        'post' => 'Napisz',
    ],
];
