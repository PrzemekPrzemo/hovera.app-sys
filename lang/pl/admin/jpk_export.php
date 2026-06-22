<?php

declare(strict_types=1);

return [
    'navigation' => 'Export JPK_FA(3)',
    'title' => 'Export JPK_FA(3) dla stajni',

    'form' => [
        'section' => 'Parametry exportu',
        'description' => 'Wybierz tenanta, rok i opcjonalnie kwartał. JPK_FA(3) zawiera wszystkie wystawione (nie-draft, nie-void) faktury VAT za wybrany okres.',
        'tenant' => 'Tenant (stajnia / transporter)',
        'year' => 'Rok',
        'quarter' => 'Kwartał',
        'quarter_full_year' => 'Cały rok',
        'quarter_helper' => 'Pomiń żeby zrobić export za cały rok.',
    ],

    'action' => [
        'download' => 'Pobierz JPK_FA(3) XML',
    ],

    'notify' => [
        'tenant_missing' => 'Nie znaleziono tenanta — wybierz z listy.',
        'failed' => 'Nie udało się wygenerować JPK',
    ],
];
