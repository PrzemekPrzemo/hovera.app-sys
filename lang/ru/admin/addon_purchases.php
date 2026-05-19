<?php

declare(strict_types=1);

return [
    'navigation' => 'Покупки add-on',
    'model' => 'Покупка add-on',
    'model_plural' => 'Покупки add-on',

    'form' => [
        'section' => [
            'basics' => 'Основная информация',
            'status' => 'Статус и оплата',
        ],
        'label' => [
            'tenant' => 'Конюшня (tenant)',
            'addon' => 'Add-on (выбрать из каталога)',
            'addon_code' => 'Код add-on',
            'addon_name' => 'Название add-on (снимок)',
            'currency' => 'Валюта',
            'amount_cents' => 'Сумма (мин. единицы)',
            'status' => 'Статус',
            'p24_link' => 'Ссылка P24 (после генерации)',
            'p24_link_none' => '— нет ссылки, используйте действие «Сгенерировать ссылку P24»',
        ],
        'helper' => [
            'amount_cents' => 'Сумма в наименьшей единице (грошах для PLN, центах для EUR). '
                .'Автозаполнение из прайса PlanAddon после выбора выше.',
        ],
    ],

    'status' => [
        'pending' => 'Ожидает оплаты',
        'paid' => 'Оплачено',
        'failed' => 'Платёж не удался',
        'cancelled' => 'Отменено',
    ],

    'table' => [
        'column' => [
            'tenant' => 'Конюшня',
            'addon' => 'Add-on',
            'amount' => 'Сумма',
            'status' => 'Статус',
            'paid_at' => 'Оплачено',
            'created_at' => 'Создано',
        ],
    ],

    'action' => [
        'generate_p24_link' => 'Сгенерировать ссылку P24',
    ],

    'notify' => [
        'link_generated' => 'Ссылка P24 сгенерирована — скопируйте ниже и отправьте клиенту',
        'link_failed' => 'Не удалось сгенерировать ссылку P24',
    ],

    'return' => [
        'paid' => 'Покупка add-on «{code}» получена — спасибо!',
        'pending' => 'Покупка add-on «{code}» проверяется.',
        'unknown' => 'Покупка add-on не найдена.',
    ],
];
