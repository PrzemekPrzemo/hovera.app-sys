<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'gus' => 'GUS BIR (REGON)',
            'gus_description' => 'API kluczyk wydaje GUS po rejestracji na https://api.stat.gov.pl. Bezpłatny. Klucz rotuje co kwartał — pamiętaj o wymianie.',
            'krs' => 'KRS (publiczne API)',
            'krs_description' => 'KRS Open Data API jest publiczne i nie wymaga konfiguracji. Hovera korzysta z https://api-krs.ms.gov.pl. Cache 30 dni.',
        ],
        'label' => [
            'gus_api_key' => 'Klucz API GUS',
            'gus_env' => 'Środowisko',
            'krs_status' => 'Status',
        ],
        'helper' => [
            'gus_api_key' => 'Klucz testowy z dokumentacji GUS: abcde12345abcde12345 (działa tylko ze środowiskiem test).',
        ],
        'options' => [
            'env_test' => 'Test (wyszukiwarkaregontest.stat.gov.pl)',
            'env_prod' => 'Produkcyjne (wyszukiwarkaregon.stat.gov.pl)',
            'krs_enabled' => '✓ Włączone (publiczne API, brak konfiguracji)',
        ],
    ],

    'action' => [
        'saved' => 'Zapisano konfigurację GUS / KRS',
    ],
];
