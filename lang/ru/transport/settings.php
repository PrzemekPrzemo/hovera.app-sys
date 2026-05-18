<?php

declare(strict_types=1);

return [
    'navigation' => 'Тарифы',
    'title' => 'Тарифы транспорта',

    'section' => [
        'rates' => 'Тарифы за километр',
        'rates_description' => 'Базовые тарифы для расчёта предложений.',
        'fuel' => 'Топливо',
        'fuel_description' => 'Топливная надбавка: если текущая цена ДТ выше базовой, добавляем разницу × расход.',
        'tax_currency' => 'Налоги и валюта',
        'routing' => 'Провайдер карт и маршрутов',
        'routing_description' => 'OpenRouteService (бесплатно) покрывает 95% случаев. Google и Mapbox требуют собственный API-ключ.',
        'payments' => 'Платежи (direct charge)',
        'payments_description' => 'URL платёжного шлюза по умолчанию и инструкции — автоматически подставляются в каждое новое предложение.',
        'payments_disclaimer' => 'Hovera НЕ принимает платежи. Клиент платит вам напрямую — Hovera только отображает указанные здесь данные на странице принятия предложения. Stripe / Przelewy24 / другое — полностью ваша ответственность, ваш счёт, ваша налоговая отчётность.',
    ],

    'form' => [
        'label' => [
            'rate_per_km' => 'Тариф за км',
            'rate_per_km_loaded' => 'Тариф за км с лошадью',
            'minimum_charge' => 'Минимальная стоимость заказа',
            'fuel_consumption_l_per_100km' => 'Расход (Л/100 км)',
            'fuel_surcharge_enabled' => 'Включить топливную надбавку',
            'fuel_base_price_pln' => 'Базовая цена ДТ',
            'vat_rate' => 'Ставка НДС',
            'currency' => 'Валюта',
            'routing_provider' => 'Провайдер маршрутов',
            'routing_api_key' => 'API-ключ',
            'default_payment_url_template' => 'Шаблон URL оплаты по умолчанию',
            'default_payment_method_label' => 'Метка способа оплаты по умолчанию',
            'payment_instructions' => 'Инструкции по оплате (резерв)',
        ],
        'helper' => [
            'rate_per_km_loaded' => 'Оставьте пустым, если совпадает с тарифом без груза.',
            'fuel_surcharge_enabled' => 'Добавляем разницу между текущей и базовой ценой.',
            'routing_api_key' => 'API-ключ для выбранного провайдера. Хранится безопасно в БД.',
            'default_payment_url_template' => 'URL вашей платёжной системы. Поддерживаются: {quote_number}, {gross_total_pln}, {customer_name}.',
            'default_payment_method_label' => 'Напр.: «Stripe», «Przelewy24», «BLIK / перевод» — показывается под кнопкой Оплатить.',
            'payment_instructions' => 'Текст на странице предложения, если URL оплаты не задан (напр., банковские реквизиты).',
        ],
        'option' => [
            'routing_provider' => [
                'ors' => 'OpenRouteService (бесплатно)',
                'mapbox' => 'Mapbox (свой ключ)',
                'google' => 'Google Maps Routes (свой ключ)',
            ],
        ],
    ],

    'action' => [
        'save' => 'Сохранить настройки',
        'saved' => 'Настройки сохранены.',
    ],
];
