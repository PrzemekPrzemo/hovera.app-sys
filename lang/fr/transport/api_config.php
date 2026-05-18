<?php

declare(strict_types=1);

return [
    'action' => [
        'test_key' => 'Tester la clé API',
    ],

    'notify' => [
        'success' => 'La clé fonctionne',
        'failure' => 'La clé ne fonctionne pas',
    ],

    'probe' => [
        'empty_key' => 'Collez une clé API avant de cliquer sur « Tester ».',
        'ok' => 'La clé :provider renvoie un itinéraire valide (distance test : :km km).',
        'unexpected_error' => 'Erreur inattendue',
    ],
];
