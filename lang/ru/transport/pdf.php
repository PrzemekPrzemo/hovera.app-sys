<?php

declare(strict_types=1);

return [
    'title' => 'Транспортное предложение :number',
    'number_label' => 'ПРЕДЛОЖЕНИЕ №',
    'issued' => 'Выставлено',
    'valid_until' => 'Действует до',

    'heading' => 'Предложение по перевозке лошадей',
    'subtitle' => 'Цена действительна на указанную дату и в течение срока действия.',

    'section' => [
        'customer' => 'Клиент',
        'route' => 'Маршрут',
        'pricing' => 'Расчёт',
        'terms' => 'Условия',
        'payment' => 'Оплата',
    ],

    'label' => [
        'name' => 'ФИО',
        'company' => 'Компания',
        'tax_id' => 'ИНН / VAT',
        'email' => 'Email',
        'phone' => 'Телефон',
        'address' => 'Адрес',
        'from' => 'Откуда',
        'to' => 'Куда',
        'date' => 'Дата',
        'distance' => 'Расстояние',
        'duration' => 'Время в пути',
        'round_trip' => 'С возвратом',
        'component' => 'Позиция',
        'amount' => 'Сумма',
        'base_cost' => 'Базовая стоимость',
        'fuel_surcharge' => 'Топливная надбавка',
        'minimum_adjustment' => 'Дополнение до минимума',
        'net_total' => 'Итого без НДС',
        'vat' => 'НДС (:rate%)',
        'gross_total' => 'Итого с НДС',
        'payment_url' => 'Ссылка для оплаты',
        'payment_method_label' => 'Метод',
        'payment_instructions' => 'Инструкции',
    ],

    'payment_disclaimer' => 'Оплата производится НАПРЯМУЮ :transporter. Hovera — посредник marketplace и НЕ принимает платежи.',

    'value' => [
        'yes' => 'Да',
        'no' => 'Нет',
    ],

    'footer' => [
        'generated' => 'Документ создан в :app',
    ],
];
