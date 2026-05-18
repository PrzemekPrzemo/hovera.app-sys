<?php

declare(strict_types=1);

return [
    'section' => [
        'personal' => 'Личные данные',
        'contact' => 'Контакт',
        'license' => 'Водительское удостоверение',
        'qualifications' => 'Дополнительные допуски',
        'qualifications_description' => 'Сертификат на перевозку животных (директива ЕС). ADR — для опасных грузов.',
        'other' => 'Прочее',
    ],

    'form' => [
        'label' => [
            'first_name' => 'Имя',
            'last_name' => 'Фамилия',
            'date_of_birth' => 'Дата рождения',
            'email' => 'Email',
            'phone' => 'Телефон',
            'license_number' => 'Номер удостоверения',
            'license_categories' => 'Категории',
            'license_expires_at' => 'Действует до',
            'has_animal_transport_cert' => 'Сертификат перевозки животных',
            'animal_transport_cert_expires_at' => 'Действует до',
            'has_adr' => 'ADR',
            'adr_expires_at' => 'Действует до',
            'hire_date' => 'Дата приёма',
            'is_active' => 'Активен',
            'sort_order' => 'Порядок',
            'notes' => 'Примечания',
        ],
        'helper' => [
            'email' => 'Адрес для уведомлений о заявках.',
        ],
    ],

    'table' => [
        'column' => [
            'full_name' => 'Водитель',
            'phone' => 'Телефон',
            'email' => 'Email',
            'license_expires_at' => 'Права до',
            'has_animal_transport_cert' => 'Перевозка жив.',
            'is_active' => 'Активен',
        ],
    ],

    'filter' => [
        'license_expiring_soon' => 'Права истекают в течение 30 дней',
    ],
];
