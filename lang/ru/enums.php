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
