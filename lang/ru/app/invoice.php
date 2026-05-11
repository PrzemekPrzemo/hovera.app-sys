<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'invoice_data' => 'Данные счёта',
            'buyer' => 'Покупатель',
            'seller' => 'Продавец (snapshot)',
            'dates' => 'Даты',
            'items' => 'Позиции',
            'notes' => 'Заметки',
        ],
        'label' => [
            'kind' => 'Тип',
            'number' => 'Номер',
            'number_placeholder' => '— присваивается при выставлении —',
            'status' => 'Статус',
            'client' => 'Клиент',
            'buyer_name' => 'Название / имя и фамилия',
            'buyer_nip' => 'NIP (опционально для физлиц)',
            'buyer_address' => 'Адрес',
            'buyer_postal_code' => 'Индекс',
            'buyer_city' => 'Город',
            'buyer_country' => 'Страна',
            'seller_name' => 'Название',
            'seller_nip' => 'NIP',
            'seller_address' => 'Адрес',
            'seller_postal_code' => 'Индекс',
            'seller_city' => 'Город',
            'seller_country' => 'Страна',
            'issued_at' => 'Выставлен',
            'sale_date' => 'Дата продажи',
            'due_at' => 'Срок оплаты',
            'item_name' => 'Название',
            'item_quantity' => 'Кол-во',
            'item_unit' => 'Ед.',
            'item_unit_price' => 'Цена ед. без НДС',
            'item_vat' => 'НДС',
            'notes_label' => 'Примечания',
        ],
    ],

    'table' => [
        'column' => [
            'number' => 'Номер',
            'kind' => 'Тип',
            'issued_at' => 'Выставлен',
            'client' => 'Покупатель',
            'total' => 'С НДС',
            'status' => 'Статус',
            'due_at' => 'Срок',
        ],
        'filter' => [
            'overdue' => 'Просрочено',
        ],
    ],

    'action' => [
        'issue' => [
            'label' => 'Выставить',
            'success' => 'Счёт выставлен',
            'failure_title' => 'Невозможно выставить счёт',
        ],
        'correct' => [
            'label' => 'Корректировка',
            'success_title' => 'Корректировка создана',
            'success_body' => 'Откройте черновик :id и отредактируйте позиции.',
            'failure_title' => 'Ошибка',
        ],
        'ksef' => [
            'label' => 'Отправить в KSeF',
            'modal_description' => 'Счёт будет подписан сертификатом конюшни и отправлен в KSeF.',
            'auth_success_title' => 'KSeF: аутентификация успешна',
            'auth_success_body' => 'Отправка содержимого счёта в подготовке (PR 4b).',
            'failure_title' => 'KSeF: ошибка',
        ],
        'email' => [
            'label' => 'Отправить на email',
            'modal_description' => 'Мы отправим ссылку на счёт на email клиента. Ссылка действительна до 90 дней (или 14 дней после срока оплаты).',
            'no_email' => 'Нет email клиента',
            'success' => 'Счёт отправлен на email клиента',
        ],
    ],
];
