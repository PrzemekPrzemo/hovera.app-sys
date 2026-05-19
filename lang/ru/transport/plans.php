<?php

declare(strict_types=1);

/**
 * Marketing source of truth: hovera.app/produkt/transport/.
 * Keys MUST mirror lang/pl/transport/plans.php.
 *
 * Перевод RU — деловой стиль, формальное "Вы". Технические имена
 * собственные (KSeF, HGV, ORS) сохраняются в оригинале.
 */
return [
    'page_title' => 'Тарифы для транспортных компаний',
    'meta_description' => 'Тарифы Hovera для компаний по перевозке лошадей: от 250 PLN/мес. 4 тарифа, 5 валют, гарантия фиксированной цены на 12 месяцев.',

    'heading' => 'Тарифы для транспортных компаний',
    'lede' => 'Выберите тариф, соответствующий масштабу Вашего бизнеса. Каждый тариф включает калькулятор предложений с HGV-маршрутизацией, PDF-предложения с публичным принятием клиентом и CRM клиентов. Бесплатный пробный период (1 месяц) начинается после успешной проверки документов.',

    'lock_in_note' => 'Договор на 12 месяцев — гарантия фиксированной цены',
    'promo_note' => 'Акция до 31.07.2026',

    'most_popular' => 'Самый популярный',

    'currency_label' => 'Валюта',
    'month_short' => 'мес.',
    'net_notice' => 'без НДС в месяц, оплата в конце периода',

    'custom_price' => 'Индивидуальная цена',
    'custom_price_note' => 'Цена согласовывается после разговора с отделом продаж',
    'price_unavailable' => 'Цена в :currency недоступна — свяжитесь с нами',

    'cta' => [
        'start_trial' => 'Начать сейчас',
        'contact' => 'Связаться с нами',
        'contact_subject' => 'Hovera Transport Enterprise — запрос',
    ],

    'audience_hint' => [
        'default' => '—',
        'small_carriers' => 'Малые компании и индивидуальные перевозчики',
        'growing_carriers' => 'Растущие компании с расширенным автопарком',
        'mid_large_carriers' => 'Средние и крупные компании',
        'enterprise' => 'Более 15 водителей / 25 транспортных средств',
    ],

    'feature' => [
        'calculator_hgv' => 'Полный калькулятор предложений с HGV-маршрутизацией (OpenRouteService)',
        'pdf_quotes_public_acceptance' => 'PDF-предложения + публичное принятие клиентом + рассылка WhatsApp/e-mail',
        'crm_clients' => 'CRM клиентов с индивидуальными тарифами для каждого клиента',
        'poi_google_import' => 'POI: собственные места + импорт из Google Maps',
        'calendar_ical' => 'Календарь перевозок + iCal-фид (Google/Apple Calendar)',
        'public_page_pl' => 'Публичная страница компании (PL)',
        'payments_csv_import' => 'Платежи и расходы с импортом CSV',
        'invoices_ksef' => 'Счета-фактуры (KSeF и другие форматы)',
        'reports_basic' => 'Отчёты: водители, клиенты, транспортные средства, денежный поток',
        'support_email_24h' => 'Поддержка по e-mail · ответ в течение 24 часов',

        'multilang_public_page' => 'Многоязычная публичная страница (PL + EN + DE)',
        'custom_rates_per_client' => 'Индивидуальные тарифы и минимальные суммы для каждого клиента',
        'auto_toll_estimation' => 'Автоматический расчёт платных дорог (ORS tollways)',
        'stop_types_dictionary' => 'Словарь типов остановок (погрузка/разгрузка/ветеринар/ночлег)',
        'public_gallery' => 'Публичная галерея с фотографиями перевозок',

        'custom_branding' => 'Собственный брендинг (логотип + цвета на публичной странице и в PDF)',
        'advanced_reports' => 'Расширенные отчёты: маржа, лучшие маршруты, популярность маршрутов',
        'export_csv_json_gdpr' => 'Экспорт всех данных в CSV/JSON (GDPR ст. 20)',
        'configurable_toll_rates' => 'Настраиваемые ставки платных дорог (легковой vs грузовой)',
        'roadmap_priority' => 'Приоритет в roadmap (голосование за функции)',

        'dedicated_environment' => 'Выделенная среда (отдельный VPS)',
        'sla_financial_99_9' => 'SLA 99,9 % с финансовой гарантией',
        'live_onboarding' => 'Онлайн-обучение с тренером (2–4 ч)',
        'data_migration_free' => 'Миграция данных — бесплатно',
        'white_label' => 'White-label (система под брендом клиента)',
        'api_rest' => 'REST API для интеграций',
        'dedicated_storage' => 'Резервное копирование на выделенное хранилище (S3 / GDrive)',
        'custom_integrations' => 'Индивидуальные интеграции (CRM / ERP / бухгалтерия)',
    ],

    'addons_heading' => 'Дополнения',
    'addons_sub' => 'Все дополнения являются глобальными — доступны независимо от выбранного тарифа.',
    'addons_table' => [
        'name' => 'Дополнение',
        'type' => 'Тип оплаты',
        'price' => 'Цена',
    ],
    'addon_type' => [
        'one_time' => 'разовая',
        'recurring_monthly' => 'ежемесячная',
    ],

    'nav' => [
        'stable_pricing' => 'Тарифы для конюшен',
        'signup' => 'Регистрация',
    ],
    'footer' => [
        'signup' => 'Регистрация',
        'terms' => 'Условия',
    ],
];
