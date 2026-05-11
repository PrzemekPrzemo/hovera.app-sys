<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'env' => 'Окружение KSeF',
            'cert_upload' => 'Сертификат — загрузка',
            'cert_upload_description' => 'Единоразово загружаете сертификат. Приватный ключ + пароль зашифрованы на уровне приложения (Laravel Crypt + AES-256).',
            'cert_current' => 'Текущий сохранённый сертификат',
        ],
        'env_options' => [
            'test' => 'Тест (ksef-test.mf.gov.pl)',
            'demo' => 'Демо (ksef-demo.mf.gov.pl)',
            'prod' => 'Прод (ksef.mf.gov.pl)',
        ],
        'identifier_options' => [
            'subject' => 'Subject сертификата (обычно для PFX)',
            'fingerprint' => 'Fingerprint (для сертификатов KSeF)',
        ],
        'cert_types' => [
            'personal' => 'Квалифицированная подпись (личная)',
            'seal' => 'Электронная печать',
            'ksef' => 'Сертификат KSeF',
        ],
        'label' => [
            'env' => 'Окружение',
            'context_nip' => 'NIP конюшни (контекст)',
            'context_nip_helper' => 'NIP, используемый при аутентификации в KSeF — тот же, что в счетах.',
            'identifier_type' => 'Тип идентификатора подписывающего',
            'tab_pfx' => 'PFX / P12',
            'tab_pem' => 'PEM (.crt + .key)',
            'cert_pfx_file' => 'Файл сертификата (.pfx / .p12)',
            'cert_pfx_password' => 'Пароль PFX',
            'cert_pfx_password_helper' => 'Пароль используется ТОЛЬКО при парсинге — НЕ сохраняется в открытом виде.',
            'cert_pem_crt' => 'Сертификат (.crt / .pem)',
            'cert_pem_key' => 'Приватный ключ (.key / .pem)',
            'cert_pem_password' => 'Пароль ключа (если зашифрован)',
            'cert_subject_cn' => 'Subject',
            'cert_subject_nip' => 'NIP в сертификате',
            'cert_issuer' => 'Издатель',
            'cert_fingerprint' => 'Fingerprint SHA-256',
            'cert_valid_to' => 'Действителен до',
            'cert_type' => 'Тип',
        ],
    ],

    'action' => [
        'pfx_saved' => 'Сертификат PFX сохранён.',
        'pfx_error_title' => 'Ошибка сертификата PFX',
        'pem_saved' => 'Сертификат PEM сохранён.',
        'pem_error_title' => 'Ошибка сертификата PEM',
        'saved' => 'Настройки KSeF сохранены',
        'cant_read_file' => 'Невозможно прочитать загруженный файл сертификата.',
    ],
];
