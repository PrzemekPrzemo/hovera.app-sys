<?php

declare(strict_types=1);

return [
    'tenant_type' => [
        'stable' => 'Конюшня',
        'transporter' => 'Транспортная компания',
    ],

    'vehicle_type' => [
        'truck' => 'Автомобиль (с двигателем)',
        'trailer' => 'Прицеп',
    ],

    'quote_status' => [
        'draft' => 'Черновик',
        'sent' => 'Отправлена',
        'accepted' => 'Принята',
        'rejected' => 'Отклонена',
        'expired' => 'Истекла',
        'withdrawn' => 'Отозвана',
    ],

    'verification_status' => [
        'pending' => 'Ожидаются документы',
        'under_review' => 'На проверке',
        'verified' => 'Подтверждено',
        'rejected' => 'Отклонено',
    ],

    'transport_lead_status' => [
        'open' => 'Открыт',
        'quoted' => 'Предложение отправлено',
        'accepted' => 'Принят',
        'rejected' => 'Отклонён',
        'expired' => 'Истёк',
        'cancelled' => 'Отменён',
    ],

    'transport_invoice_kind' => [
        'fv' => 'Счёт-фактура (НДС)',
        'fv_proforma' => 'Счёт пр​о форма',
        'fv_korekta' => 'Корректировочный счёт',
    ],

    'transport_invoice_status' => [
        'draft' => 'Черновик',
        'issued' => 'Выставлен',
        'paid' => 'Оплачен',
        'overdue' => 'Просрочен',
        'void' => 'Аннулирован',
        'cancelled' => 'Отменён',
    ],

    'transporter_document_type' => [
        // @todo native review — машинный перевод с PL/EN.
        'company_registration' => 'Регистрация компании',
        'company_registration_description' => 'Выписка из реестра (KRS / CEIDG в PL, эквивалент в других странах — PDF/JPG).',

        // Legacy — устаревшие, скрыты в новом UI, сохранены для обратной совместимости.
        'animal_transport_cert' => 'Сертификат перевозки животных (legacy)',
        'animal_transport_cert_description' => 'Старый сертификат ЕС 1/2005 — заменён на Сертификат одобрения транспортного средства PLW.',
        'insurance_ocp' => 'Страхование ответственности перевозчика (legacy)',
        'insurance_ocp_description' => 'Заменено новой записью «Страхование ответственности перевозчика» в списке PLW.',
        'insurance_ocs' => 'Страхование груза',
        'insurance_ocs_description' => 'Страхование ущерба перевозимому животному. Необязательно, но рекомендуется.',
        'vehicle_registration' => 'Свидетельство о регистрации ТС (legacy)',
        'vehicle_registration_description' => 'Заменено Сертификатом одобрения транспортного средства PLW.',

        // PLW — польский режим внутрисоюзной перевозки живых животных.
        // @todo native review — машинный перевод польских регуляторных текстов.
        'road_carrier_license' => 'Разрешение на осуществление профессии Автодорожного перевозчика',
        'road_carrier_license_description' => 'Выдаётся GITD или старостой согласно Регл. ЕС 1071/2009 и закону Польши об автодорожном транспорте 2001 г.',
        'pwl_authorization_type1' => 'Разрешение перевозчика PLW — Тип 1 (< 8 ч)',
        'pwl_authorization_type1_description' => 'Авторизация PIW (ветинспекция) для перевозок до 8 часов. Выбирайте Тип 1, если выполняете только короткие рейсы.',
        'pwl_authorization_type2' => 'Разрешение перевозчика PLW — Тип 2 (> 8 ч)',
        'pwl_authorization_type2_description' => 'Авторизация PIW для длинных перевозок свыше 8 часов. Покрывает также использование Типа 1.',
        'pwl_driver_handler_certificate' => 'Лицензии для водителей и обслуживающих лиц (PLW)',
        'pwl_driver_handler_certificate_description' => 'Статья 6 Регл. ЕС 1/2005 — свидетельства компетентности водителей и лиц, обслуживающих животных. Загрузите комплект для всей команды.',
        'pwl_vehicle_approval_certificate' => 'Сертификат одобрения транспортного средства (PLW)',
        'pwl_vehicle_approval_certificate_description' => 'Статья 18 (< 8 ч) или 19 (> 8 ч) Регл. ЕС 1/2005. Обязателен для каждого транспортного средства, используемого для перевозки лошадей.',
        'wash_disinfection_log' => 'Журнал мойки и дезинфекции транспортного средства',
        'wash_disinfection_log_description' => 'Требование закона Польши об охране здоровья животных 2004 г. Загрузите актуальные записи за последние 12 месяцев.',
        'carrier_liability_insurance' => 'Страхование ответственности перевозчика',
        'carrier_liability_insurance_description' => 'Полис ответственности автоперевозчика. Проверяем дату окончания и страховую сумму.',

        'other' => 'Иной документ',
        'other_description' => 'Лицензия ЕС, справка инспекции труда, дополнительный полис cargo и т. п.',
    ],

    'boarding_frequency' => [
        'daily' => 'Ежедневно',
        'monthly' => 'Ежемесячно',
        'per_use' => 'За использование',
        'once' => 'Единоразово',
    ],

    'calendar_entry_status' => [
        'requested' => 'Заявка',
        'confirmed' => 'Подтверждено',
        'cancelled' => 'Отменено',
        'completed' => 'Завершено',
        'no_show' => 'Неявка',
    ],

    'calendar_entry_type' => [
        'lesson_individual' => 'Индивидуальное занятие',
        'lesson_group' => 'Групповое занятие',
        'training' => 'Тренировка',
        'care' => 'Уход (вет/коваль)',
        'event' => 'Мероприятие',
        'block' => 'Блокировка',
    ],

    'health_record_type' => [
        'vaccination' => 'Вакцинация',
        'deworming' => 'Дегельминтизация',
        'vet_visit' => 'Визит ветеринара',
        'farrier' => 'Коваль',
        'dentist' => 'Стоматолог',
        'check_up' => 'Профилактический осмотр',
        'medication' => 'Лекарства',
        'other' => 'Другое',
    ],

    'horse_document_kind' => [
        'passport' => 'Паспорт лошади',
        'contract' => 'Договор пансиона',
        'insurance' => 'Полис / страхование',
        'vaccine_book' => 'Книга прививок',
        'ownership_proof' => 'Документ о праве собственности',
        'competition_licence' => 'Спортивная лицензия',
        'vet_certificate' => 'Ветеринарная справка',
        'other' => 'Другое',
    ],

    'invoice_kind' => [
        'fv' => 'Счёт-фактура НДС',
        'fv_proforma' => 'Счёт-проформа',
        'fv_korekta' => 'Корректировочный счёт',
    ],

    'invoice_status' => [
        'draft' => 'Черновик',
        'issued' => 'Выставлен',
        'paid' => 'Оплачен',
        'overdue' => 'Просрочен',
        'void' => 'Аннулирован',
        'cancelled' => 'Скорректирован',
    ],

    'pass_status' => [
        'active' => 'Активный',
        'exhausted' => 'Использован',
        'expired' => 'Истёк',
        'cancelled' => 'Отменён',
    ],

    'payment_provider' => [
        'none' => 'Нет (оплата офлайн)',
        'stub' => 'Тест (разработчик)',
        'p24' => 'Przelewy24',
        'payu' => 'PayU',
        'stripe' => 'Stripe',
        'mollie' => 'Mollie',
    ],

    'payment_status' => [
        'pending' => 'Ожидает',
        'processing' => 'Обработка',
        'succeeded' => 'Оплачен',
        'failed' => 'Не удалось',
        'refunded' => 'Возвращён',
    ],

    'recurrence_pattern' => [
        'daily' => 'Ежедневно',
        'weekly' => 'Еженедельно',
        'monthly' => 'Ежемесячно',
    ],

    'stable_activity_type' => [
        'feeding' => 'Кормление',
        'grooming' => 'Чистка / уход',
        'turnout' => 'Выпуск в леваду',
        'exercise' => 'Работа с лошадью',
        'box_cleaning' => 'Уборка денника',
        'transport_event' => 'Выезд / мероприятие',
        'other' => 'Другое',
    ],

    'feeding_meal' => [
        'breakfast' => 'Утро',
        'midday' => 'Полдень',
        'evening' => 'Вечер',
        'night' => 'Ночь',
    ],
];
