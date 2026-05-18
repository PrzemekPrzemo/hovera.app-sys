<?php

declare(strict_types=1);

return [
    'tenant_type' => [
        'stable' => 'Конюшня',
        'transporter' => 'Транспортная компания',
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
        'company_registration' => 'Регистрация компании',
        'company_registration_description' => 'Выписка из реестра (PDF/JPG).',
        'animal_transport_cert' => 'Сертификат перевозки животных',
        'animal_transport_cert_description' => 'Регламент ЕС 1/2005 — обязателен для перевозки лошадей.',
        'insurance_ocp' => 'Страхование ответственности перевозчика',
        'insurance_ocp_description' => 'Страхование гражданской ответственности автоперевозчика.',
        'insurance_ocs' => 'Страхование груза',
        'insurance_ocs_description' => 'Страхование ущерба перевозимому животному.',
        'vehicle_registration' => 'Свидетельство о регистрации ТС',
        'vehicle_registration_description' => 'Скан свидетельства — проверяем дату следующего ТО.',
        'other' => 'Иной документ',
        'other_description' => 'Лицензия ЕС, справка инспекции труда и т. п.',
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
