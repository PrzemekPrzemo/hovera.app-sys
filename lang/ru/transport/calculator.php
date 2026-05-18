<?php

declare(strict_types=1);

return [
    'navigation' => 'Калькулятор',
    'title' => 'Калькулятор стоимости транспорта',

    'section' => [
        'route' => 'Маршрут',
        'options' => 'Опции',
    ],

    'form' => [
        'label' => [
            'from_address' => 'Адрес погрузки',
            'to_address' => 'Адрес выгрузки',
            'loaded' => 'С лошадью (с грузом)',
            'round_trip' => 'С возвратом',
            'avoid_tolls' => 'Объезжать платные дороги',
            'avoid_ferries' => 'Объезжать паромы',
            'profile' => 'Профиль ТС',
        ],
        'placeholder' => [
            'from_address' => 'напр. Конюшня, ул. Главная 1, Москва',
            'to_address' => 'напр. Санкт-Петербург, ул. Спорта 1',
        ],
        'option' => [
            'profile' => [
                'truck' => 'Грузовой (HGV)',
                'car' => 'Легковой',
            ],
        ],
    ],

    'action' => [
        'submit' => 'Рассчитать',
        'calculated' => 'Расчёт выполнен.',
        'failed' => 'Не удалось рассчитать',
    ],

    'result' => [
        'heading' => 'Результат расчёта',
        'from' => 'Откуда',
        'to' => 'Куда',
        'distance' => 'Расстояние',
        'duration' => 'Время в пути',
        'rate_used' => 'Применённый тариф',
        'base_cost' => 'Базовая стоимость',
        'fuel_surcharge' => 'Топливная надбавка',
        'minimum_adjustment' => 'Дополнение до минимума',
        'net_total' => 'Итого без НДС',
        'vat' => 'НДС (:rate%)',
        'gross_total' => 'Итого с НДС',
        'routing_via' => 'Маршрут через: :provider',
    ],

    'error' => [
        'no_tenant' => 'Нет активного арендатора — войдите снова.',
        'unknown' => 'Непредвиденная ошибка. Попробуйте ещё раз.',
    ],
];
