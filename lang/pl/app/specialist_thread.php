<?php

declare(strict_types=1);

return [
    'nav' => 'Wiadomości — specjaliści',
    'model' => 'Wątek ze specjalistą',
    'model_plural' => 'Wątki ze specjalistami',
    'messages' => 'Wiadomości',
    'unverified' => 'niezweryfikowany',

    'form' => [
        'specialist' => 'Specjalista',
        'specialist_hint' => 'Lista zawiera tylko specjalistów powiązanych z Twoją stajnią (zaproszonych przez Hoverę).',
        'horse' => 'Koń (opcjonalnie)',
        'horse_placeholder' => '— wątek ogólny —',
        'subject' => 'Temat',
        'body' => 'Treść wiadomości',
    ],

    'table' => [
        'subject' => 'Temat',
        'specialist' => 'Specjalista',
        'last_message' => 'Ostatnia wiadomość',
    ],

    'action' => [
        'new' => 'Nowy wątek',
        'open' => 'Otwórz',
        'reply' => 'Odpowiedz',
    ],

    'sender' => [
        'specialist' => 'Specjalista',
        'stable' => 'Stajnia',
    ],

    'error' => [
        'no_context' => 'Brak kontekstu stajni — odśwież stronę i spróbuj ponownie.',
    ],
];
