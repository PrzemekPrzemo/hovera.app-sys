<?php

declare(strict_types=1);

return [
    'action' => [
        'test_key' => 'API-Schlüssel testen',
    ],

    'notify' => [
        'success' => 'Schlüssel funktioniert',
        'failure' => 'Schlüssel funktioniert nicht',
    ],

    'probe' => [
        'empty_key' => 'Schlüssel einfügen, bevor Sie auf „Testen" klicken.',
        'ok' => 'Schlüssel :provider liefert eine gültige Route (Testdistanz: :km km).',
        'unexpected_error' => 'Unerwarteter Fehler',
    ],
];
