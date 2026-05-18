<?php

declare(strict_types=1);

return [
    'title' => 'Транспортное предложение :number',
    'quote_number' => 'НОМЕР ПРЕДЛОЖЕНИЯ',
    'accepted_banner' => 'Спасибо! Предложение принято — перевозчик свяжется.',
    'rejected_banner' => 'Предложение отклонено. Спасибо за ответ.',
    'already_accepted' => 'Это предложение уже принято.',
    'already_rejected' => 'Это предложение уже отклонено.',

    'label' => [
        'from' => 'Откуда',
        'to' => 'Куда',
        'date' => 'Дата',
        'distance' => 'Расстояние',
        'valid_until' => 'Действует до',
        'net' => 'Без НДС',
        'vat' => 'НДС (:rate%)',
        'gross' => 'К оплате',
    ],

    'action' => [
        'accept' => 'Принять предложение',
        'reject' => 'Отклонить',
    ],

    'footer' => 'Защищённая страница, предоставлено :app',

    'disclaimer_intermediary_html' => '<strong>Принимая предложение, вы заключаете договор НЕПОСРЕДСТВЕННО с :transporter_name :transporter_nip.</strong> Hovera — посредник маркетплейса, НЕ сторона договора, НЕ перевозчик и НЕ отвечает за исполнение. Ознакомьтесь с <a href="/regulamin-marketplace" target="_blank" style="color:inherit;text-decoration:underline;">регламентом транспортного маркетплейса</a>.',
];
