<?php

declare(strict_types=1);

return [
    'action' => [
        'test_key' => 'Sprawdź klucz API',
    ],

    'notify' => [
        'success' => 'Klucz działa',
        'failure' => 'Klucz nie działa',
    ],

    'probe' => [
        'empty_key' => 'Wklej klucz API zanim klikniesz „Sprawdź".',
        'ok' => 'Klucz :provider zwraca poprawną trasę (dystans testowy: :km km).',
        'unexpected_error' => 'Nieoczekiwany błąd',
    ],
];
