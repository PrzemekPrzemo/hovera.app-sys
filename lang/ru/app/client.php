<?php

declare(strict_types=1);

return [
    'types' => [
        'individual' => 'Физическое лицо',
        'family' => 'Семья',
        'organisation' => 'Компания / организация',
    ],
    'types_short' => [
        'individual' => 'Физлицо',
        'family' => 'Семья',
        'organisation' => 'Компания',
    ],

    'form' => [
        'section' => [
            'data' => 'Данные клиента',
            'armir' => 'Идентификация владельца лошади (ARMiR)',
            'armir_description' => 'Требуется для владельцев лошадей, зарегистрированных в Центральной базе непарнокопытных. EP (номер производителя, присвоенный ARMiR) — если его нет, введите PESEL.',
            'address' => 'Адрес',
            'rodo' => 'GDPR',
            'notes' => 'Заметки',
        ],
        'label' => [
            'type' => 'Тип',
            'name' => 'Имя и фамилия / Название',
            'phone' => 'Телефон',
            'tax_id' => 'NIP / VAT ID',
            'armir_producer_id' => '№ EP (номер производителя ARMiR)',
            'armir_producer_id_placeholder' => 'напр. 026123456789',
            'pesel' => 'PESEL',
            'street' => 'Улица и номер',
            'postal_code' => 'Почтовый индекс',
            'city' => 'Город',
            'country' => 'Страна',
            'rodo_consent_at' => 'Согласие GDPR получено',
            'rodo_consent_source' => 'Источник согласия',
            'notes' => 'Внутренние заметки',
        ],
        'helper' => [
            'armir_producer_id' => 'Номер производителя, присвоенный в ARMiR при регистрации лошади.',
            'pesel' => 'Введите только если у владельца нет присвоенного EP в ARMiR.',
        ],
        'gus' => [
            'lookup_label' => 'Получить из GUS',
            'invalid_nip' => 'Неверный NIP (контрольная сумма).',
            'not_found' => 'Компания в GUS не найдена.',
            'success' => 'Данные получены из GUS.',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Название',
            'type' => 'Тип',
            'phone' => 'Телефон',
            'horses_count' => 'Лошади',
            'rodo' => 'GDPR',
            'created_at' => 'Добавлен',
        ],
    ],

    'action' => [
        'issue_portal_link' => [
            'label' => 'Скопировать ссылку портала',
            'modal_heading' => 'Создать ссылку для входа для :name?',
            'modal_description' => 'Создаёт одноразовую magic-ссылку (TTL 30 мин). Можно скопировать и отправить клиенту вручную, напр. по SMS или в мессенджере. Не требует email.',
            'success_title' => 'Ссылка для входа создана',
        ],
        'email_portal_link' => [
            'label' => 'Отправить ссылку на email',
            'modal_heading' => 'Отправить ссылку для входа :name?',
            'modal_description' => 'Мы отправим письмо со ссылкой для входа на адрес :email. Ссылка действительна 30 минут, одноразово.',
            'success_title' => 'Ссылка отправлена',
            'success_body' => 'Email со ссылкой для входа отправлен на :email.',
            'no_email' => 'У клиента нет адреса email в профиле.',
        ],
    ],
];
