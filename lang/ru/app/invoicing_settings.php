<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'numbering' => 'Нумерация счетов',
            'numbering_description' => 'Плейсхолдеры: {seq}, {seq:NN} (напр. {seq:4} → 0001), {YYYY}, {YY}, {MM}, {M}, {DD}, {prefix}.',
            'seller' => 'Данные продавца (snapshot в счетах)',
            'seller_description' => 'Эти данные будут сохранены в каждом новом счёте в момент создания. Изменение данных конюшни не повлияет на уже выставленные счета.',
        ],
        'label' => [
            'template_fv' => 'Шаблон счёта-фактуры',
            'template_pro' => 'Шаблон проформы',
            'template_kor' => 'Шаблон корректировки',
            'prefix' => 'Префикс (плейсхолдер {prefix})',
            'prefix_placeholder' => 'напр. STW',
            'reset_interval' => 'Сброс нумерации',
            'default_due_days' => 'Срок оплаты по умолчанию (дней)',
            'seller_name' => 'Название продавца',
            'seller_nip' => 'NIP продавца',
            'seller_address' => 'Адрес',
            'seller_postal_code' => 'Почтовый индекс',
            'seller_city' => 'Город',
        ],
    ],

    'action' => [
        'saved' => 'Настройки счетов сохранены',
    ],

    'reset_options' => [
        'yearly' => 'Ежегодно (старт с 1 в новом году)',
        'monthly' => 'Ежемесячно (старт с 1 каждый месяц)',
        'never' => 'Никогда (непрерывная нумерация)',
    ],
];
