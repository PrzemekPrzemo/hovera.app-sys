<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'gus' => 'GUS BIR (REGON)',
            'gus_description' => 'API kluczyk wydaje GUS po rejestracji na https://api.stat.gov.pl. Bezpłatny. Klucz rotuje co kwartał — pamiętaj o wymianie.',
            'ceidg' => 'CEIDG (Centralna Ewidencja Działalności Gospodarczej)',
            'ceidg_description' => 'Rejestr JDG (jednoosobowych działalności). Bezpłatny token JWT po rejestracji na https://datastore.ceidg.gov.pl. Bez tokenu lookup pomija CEIDG i używa tylko GUS+KRS.',
            'krs' => 'KRS (publiczne API)',
            'krs_description' => 'KRS Open Data API jest publiczne i nie wymaga konfiguracji. Hovera korzysta z https://api-krs.ms.gov.pl. Cache 30 dni.',
            'vies' => 'VIES (walidacja NIP UE)',
            'vies_description' => 'Publiczne API Komisji Europejskiej do weryfikacji numerów VAT zagranicznych firm (klienci spoza Polski). Bez klucza. Domyślny endpoint: https://ec.europa.eu/taxation_customs/vies/rest-api — wpisz inny URL TYLKO jeśli używasz proxy lub mirrora.',
        ],
        'label' => [
            'gus_api_key' => 'Klucz API GUS',
            'gus_env' => 'Środowisko',
            'ceidg_api_token' => 'Token CEIDG (JWT)',
            'krs_status' => 'Status',
            'vies_base_url' => 'Endpoint VIES (opcjonalnie)',
            'vies_base_url_placeholder' => 'https://ec.europa.eu/taxation_customs/vies/rest-api',
        ],
        'helper' => [
            'gus_api_key' => 'Klucz testowy z dokumentacji GUS: abcde12345abcde12345 (działa tylko ze środowiskiem test).',
            'ceidg_api_token' => 'Long-lived JWT z panelu CEIDG. Wymagany scope: dane firmy + adres.',
            'vies_base_url' => 'Zostaw puste żeby używać domyślnego endpointa Komisji Europejskiej. Po zapisie cache VIES jest czyszczony.',
        ],
        'options' => [
            'env_test' => 'Test (wyszukiwarkaregontest.stat.gov.pl)',
            'env_prod' => 'Produkcyjne (wyszukiwarkaregon.stat.gov.pl)',
            'krs_enabled' => '✓ Włączone (publiczne API, brak konfiguracji)',
        ],
    ],

    'action' => [
        'saved' => 'Zapisano konfigurację GUS / CEIDG / KRS / VIES',
    ],
];
