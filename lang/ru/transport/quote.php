<?php

declare(strict_types=1);

return [
    'section' => [
        'header' => 'Заголовок',
        'customer' => 'Клиент',
        'route' => 'Маршрут',
        'resources' => 'Ресурсы (опционально)',
        'pricing' => 'Расчёт',
        'terms' => 'Условия и заметки',
    ],

    'form' => [
        'label' => [
            'number' => 'Номер',
            'status' => 'Статус',
            'valid_until' => 'Действует до',
            'customer_name' => 'ФИО',
            'customer_email' => 'Email',
            'customer_phone' => 'Телефон',
            'customer_company' => 'Компания',
            'customer_tax_id' => 'ИНН / VAT',
            'customer_address' => 'Адрес для счёта',
            'pickup_address' => 'Адрес погрузки',
            'dropoff_address' => 'Адрес выгрузки',
            'preferred_date' => 'Дата',
            'preferred_time' => 'Время',
            'round_trip' => 'С возвратом',
            'loaded' => 'С лошадью',
            'vehicle' => 'ТС',
            'driver' => 'Водитель',
            'distance_km' => 'Расстояние',
            'rate_per_km' => 'Тариф',
            'duration_seconds' => 'Время (с)',
            'base_cost' => 'Базовая стоимость',
            'fuel_surcharge' => 'Топливная надбавка',
            'minimum_adjustment' => 'Доплата до минимума',
            'net_total' => 'Без НДС',
            'vat_rate' => 'Ставка НДС',
            'vat_amount' => 'Сумма НДС',
            'gross_total' => 'С НДС',
            'currency' => 'Валюта',
            'routing_provider' => 'Источник маршрута',
            'terms' => 'Коммерческие условия',
            'notes' => 'Внутренние заметки',
        ],
        'helper' => [
            'terms' => 'Видно клиенту на предложении / PDF.',
            'notes' => 'Только для команды — не передаётся клиенту.',
        ],
    ],

    'table' => [
        'column' => [
            'number' => 'Номер',
            'customer' => 'Клиент',
            'route' => 'Маршрут',
            'preferred_date' => 'Дата',
            'gross_total' => 'С НДС',
            'status' => 'Статус',
            'created_at' => 'Создано',
        ],
    ],

    'action' => [
        'send' => 'Отправить клиенту',
        'withdraw' => 'Отозвать',
        'download_pdf' => 'Скачать PDF',
        'issue_invoice' => 'Выставить счёт',
    ],

    'notify' => [
        'sent' => 'Предложение отправлено',
        'sent_body' => 'Предложение :number отправлено на :email с PDF во вложении.',
        'sent_no_email' => 'Предложение сохранено, но письмо не отправлено — проверьте SMTP "transport".',
        'sent_no_customer_email' => 'Предложение :number готово (нет email клиента — скачайте PDF и отправьте вручную).',
        'withdrawn' => 'Предложение отозвано',
        'verification_required' => 'Аккаунт не верифицирован',
        'verification_required_body' => 'Чтобы отправить предложение клиенту, нужно пройти верификацию — загрузите обязательные документы.',
        'open_documents' => 'Открыть документы',
        'invoice_issued' => 'Счёт выставлен',
        'invoice_issued_body' => 'Счёт :number создан из этого предложения.',
        'invoice_failed' => 'Не удалось выставить счёт',
    ],
];
