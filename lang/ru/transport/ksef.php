<?php

declare(strict_types=1);

return [
    'section' => [
        'title' => 'KSeF (польская электронная фактуризация)',
        'description' => 'Интеграция KSeF для транспортных счетов — выставленных вами.',
        'disclaimer' => 'Токен KSeF вы получаете в своём личном кабинете KSeF (mf.gov.pl). '
            .'Hovera только пересылает ваши счета — это ВАШ токен, ВАШ NIP, '
            .'ВАША бухгалтерская ответственность. Hovera не является стороной ваших транспортных '
            .'договоров и не выставляет ваши счета (см. docs/TRANSPORT.md §12).',
        'invoice_title' => 'KSeF — статус отправки',
        'invoice_description' => 'Информация об отправке в KSeF (если включено).',
    ],

    'form' => [
        'label' => [
            'nip' => 'NIP отправителя (ваш)',
            'environment' => 'Среда KSeF',
            'token' => 'Авторизационный токен KSeF',
            'enabled' => 'Включить интеграцию KSeF',
            'invoice_status' => 'Статус в KSeF',
            'reference_number' => 'Номер ссылки KSeF',
            'submitted_at' => 'Отправлено',
        ],
        'helper' => [
            'nip' => '10-значный польский NIP.',
            'token_empty' => 'Вставьте токен из панели MF. Храним в зашифрованном виде.',
            'token_set' => 'Токен сохранён. Введите новое значение для замены.',
            'enabled' => 'После включения появится действие „Отправить в KSeF".',
        ],
        'option' => [
            'environment' => [
                'test' => 'Тест (ksef-test.mf.gov.pl)',
                'demo' => 'Демо (ksef-demo.mf.gov.pl)',
                'production' => 'Продакшн (ksef.mf.gov.pl)',
            ],
        ],
    ],

    'action' => [
        'submit' => 'Отправить в KSeF',
        'submit_confirm' => 'Отправить счёт в KSeF? Действие необратимо.',
        'submit_bulk' => 'Отправить выбранное в KSeF',
        'submit_bulk_confirm' => 'Отправить выбранные счета (макс. 50) в KSeF?',
        'refresh' => 'Обновить статус KSeF',
        'test_connection' => 'Проверить соединение с KSeF',
    ],

    'notify' => [
        'submitted' => 'Счёт отправлен в KSeF.',
        'submit_failed' => 'Ошибка отправки в KSeF.',
        'status_refreshed' => 'Статус KSeF обновлён.',
        'not_configured' => 'KSeF не настроен.',
        'unknown_error' => 'Неизвестная ошибка KSeF.',
        'test_ok' => 'Соединение с KSeF работает.',
        'test_failed' => 'Ошибка соединения с KSeF.',
        'bulk_done' => 'Массовая отправка завершена.',
        'bulk_done_body' => 'Успешно: :ok. Ошибки: :fail.',
    ],

    'status' => [
        'not_submitted' => 'Не отправлено',
        'submitted' => 'Отправлено',
        'accepted' => 'Принято',
        'rejected' => 'Отклонено',
        'error' => 'Ошибка',
    ],

    'table' => [
        'column' => [
            'status' => 'KSeF',
        ],
    ],
];
