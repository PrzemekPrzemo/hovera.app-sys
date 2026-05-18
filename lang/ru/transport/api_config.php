<?php

declare(strict_types=1);

return [
    'action' => [
        'test_key' => 'Проверить ключ API',
    ],

    'notify' => [
        'success' => 'Ключ работает',
        'failure' => 'Ключ не работает',
    ],

    'probe' => [
        'empty_key' => 'Вставьте ключ API перед нажатием «Проверить».',
        'ok' => 'Ключ :provider возвращает корректный маршрут (тестовое расстояние: :km км).',
        'unexpected_error' => 'Непредвиденная ошибка',
    ],
];
