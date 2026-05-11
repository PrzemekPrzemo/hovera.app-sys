<?php

declare(strict_types=1);

return [
    'navigation' => 'KSeF (hovera)',
    'title' => 'KSeF — конфигурация hovera',

    'form' => [
        'section' => [
            'env' => 'Окружение',
            'cert_upload' => 'Сертификат налогоплательщика',
            'cert_upload_description' => 'Загрузите сертификат hovera как плательщика НДС (PFX с паролем либо пара .crt + .key). Сертификат вместе с паролем шифруется AES-256-CBC + HMAC через Laravel Crypt.',
            'cert_current' => 'Текущий сертификат',
        ],
        'label' => [
            'env' => 'Окружение KSeF',
            'context_nip' => 'NIP hovera (контекст налогоплательщика)',
            'context_nip_helper' => 'NIP компании, выставляющей счета — используется в заголовке Faktura/Podmiot1.',
            'identifier_type' => 'Идентификация в AuthTokenRequest',
            'tab_pfx' => 'Файл .pfx / .p12',
            'tab_pem' => 'Пара .crt + .key',
            'cert_pfx_file' => 'Файл PFX',
            'cert_pfx_password' => 'Пароль PFX',
            'cert_pfx_password_helper' => 'Пароль, с которым был создан PFX — необходим для расшифровки.',
            'cert_pem_crt' => 'Сертификат (.crt PEM)',
            'cert_pem_key' => 'Приватный ключ (.key PEM)',
            'cert_pem_password' => 'Пароль ключа (опционально)',
            'cert_subject_cn' => 'CN',
            'cert_subject_nip' => 'NIP в сертификате',
            'cert_issuer' => 'Издатель',
            'cert_fingerprint' => 'Fingerprint SHA-256',
            'cert_valid_to' => 'Действителен до',
        ],
        'env_options' => [
            'test' => 'Тест (ksef-test.mf.gov.pl)',
            'demo' => 'Демо (ksef-demo.mf.gov.pl)',
            'production' => 'Прод (ksef.mf.gov.pl)',
        ],
        'identifier_options' => [
            'subject' => 'Subject (DN из cert)',
            'fingerprint' => 'Fingerprint SHA-256',
        ],
    ],

    'action' => [
        'saved' => 'Конфигурация сохранена.',
        'save_button' => 'Сохранить конфигурацию',
        'pfx_saved' => 'Сертификат PFX сохранён.',
        'pfx_error_title' => 'Невозможно прочитать PFX',
        'pem_saved' => 'Сертификат PEM сохранён.',
        'pem_error_title' => 'Невозможно прочитать пару PEM',
        'cant_read_file' => 'Невозможно прочитать загруженный файл.',
    ],

    'status' => [
        'pending' => 'Ожидает',
        'sent' => 'Отправлено',
        'accepted' => 'Принято',
        'rejected' => 'Отклонено',
    ],
];
