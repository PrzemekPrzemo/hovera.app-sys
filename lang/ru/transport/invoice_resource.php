<?php

declare(strict_types=1);

return [
    'navigation' => 'Счета',

    'section' => [
        'header' => 'Заголовок',
        'parties' => 'Стороны',
        'amounts' => 'Суммы',
        'dates' => 'Даты',
        'route' => 'Маршрут',
        'notes' => 'Заметки',
    ],

    'form' => [
        'label' => [
            'seller' => 'Продавец',
            'buyer' => 'Покупатель',
            'net_total' => 'Без НДС',
            'vat_total' => 'НДС',
            'gross_total' => 'С НДС',
        ],
    ],

    'table' => [
        'column' => [
            'number' => 'Номер',
            'kind' => 'Тип',
            'buyer' => 'Покупатель',
            'issued_at' => 'Выставлен',
            'due_at' => 'Срок',
            'total' => 'С НДС',
            'status' => 'Статус',
        ],
    ],

    'action' => [
        'download_pdf' => 'Скачать PDF',
        'send_email' => 'Отправить по email',
        'mark_paid' => 'Отметить оплаченным',
    ],

    'notify' => [
        'sent' => 'Счёт отправлен',
        'sent_body' => 'Счёт :number отправлен на :email с PDF во вложении.',
        'no_buyer_email' => 'У покупателя нет email — скачайте PDF и отправьте вручную.',
        'email_failed' => 'Отправка не удалась',
        'marked_paid' => 'Счёт отмечен как оплачен',
    ],
];
