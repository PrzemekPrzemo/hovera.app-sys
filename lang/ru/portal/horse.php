<?php

declare(strict_types=1);

return [
    'title' => ':horse — :tenant',
    'back' => '← Вернуться в портал',

    'info' => [
        'breed' => 'Порода',
        'sex' => 'Пол',
        'color' => 'Масть',
        'age' => 'Возраст',
        'age_value' => ':years л. (:year)',
        'microchip' => 'Микрочип',
        'passport' => 'Паспорт',
    ],

    'sections' => [
        'boarding' => 'Пансион и расходы',
        'feeding_plan' => 'План кормления',
        'photos' => 'Галерея фото',
        'activities' => 'Что мы делаем с вашей лошадью',
        'messages' => 'Сообщения от конюшни',
        'documents' => 'Документы',
        'health' => 'Ветеринарная история',
    ],

    'feeding_plan' => [
        'disclaimer' => 'План кормления устанавливает конюшня. Изменения согласовывайте по email или в разделе «Сообщения».',
    ],

    'box' => [
        'pill' => '🏠 Денник :label',
        'monthly_suffix' => '/мес.',
        'monthly_label' => 'пансион: :rate',
    ],

    'services' => [
        'heading' => 'Начисляемые услуги',
        'col_item' => 'Позиция',
        'col_price' => 'Цена',
        'col_frequency' => 'Частота',
        'col_monthly' => '~мес.',
        'price_per_unit' => ':amount zł / :unit',
    ],

    'cost' => [
        'monthly_label' => 'Ориентировочная месячная стоимость:',
        'monthly_disclaimer' => 'Без услуг «за использование» и разовых — они появляются только когда начисляются.',
    ],

    'messages' => [
        'sent_flash' => '✓ Сообщение отправлено — конюшня получила email-уведомление.',
        'subject_placeholder' => 'Тема (опционально)',
        'body_placeholder' => 'Напишите что-нибудь конюшне…',
        'send' => 'Отправить',
        'you' => 'Вы',
        'empty' => 'Нет сообщений — напишите первое.',
        'attachment_fallback' => 'вложение',
    ],

    'documents' => [
        'uploaded_flash' => '✓ Документ загружен.',
        'deleted_flash' => '✓ Документ удалён.',
        'name_placeholder' => 'Название документа',
        'description_placeholder' => 'Описание (опционально)',
        'upload' => 'Загрузить документ',
        'uploaded_by_stable' => 'Конюшня',
        'uploaded_by_you' => 'Вы',
        'valid_until' => 'действителен до:',
        'download' => '📥 Скачать',
        'delete' => 'Удалить',
        'delete_confirm' => 'Удалить документ?',
        'empty' => 'Нет документов. Загрузите первый.',
    ],

    'health' => [
        'performed_by_label' => 'Выполнил: :name',
        'next_due_label' => 'Следующая процедура: :date',
        'overdue_pill' => 'Просрочено',
        'soon_pill' => 'Скоро',
        'empty' => 'Нет ветеринарных записей.',
    ],
];
