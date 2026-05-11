<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'gus' => 'GUS BIR (REGON)',
            'gus_description' => 'API-ключ выдаёт GUS после регистрации на https://api.stat.gov.pl. Бесплатно. Ключ ротируется каждый квартал — не забудьте обновить.',
            'krs' => 'KRS (публичное API)',
            'krs_description' => 'KRS Open Data API публичное и не требует настройки. Hovera использует https://api-krs.ms.gov.pl. Кэш 30 дней.',
        ],
        'label' => [
            'gus_api_key' => 'Ключ API GUS',
            'gus_env' => 'Окружение',
            'krs_status' => 'Статус',
        ],
        'helper' => [
            'gus_api_key' => 'Тестовый ключ из документации GUS: abcde12345abcde12345 (работает только с тестовым окружением).',
        ],
        'options' => [
            'env_test' => 'Тест (wyszukiwarkaregontest.stat.gov.pl)',
            'env_prod' => 'Прод (wyszukiwarkaregon.stat.gov.pl)',
            'krs_enabled' => '✓ Включено (публичное API, без настройки)',
        ],
    ],

    'action' => [
        'saved' => 'Конфигурация GUS / KRS сохранена',
    ],
];
