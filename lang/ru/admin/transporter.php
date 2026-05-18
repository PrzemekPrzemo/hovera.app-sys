<?php

declare(strict_types=1);

return [
    'navigation' => 'Транспортные компании',

    'model' => [
        'singular' => 'транспортная компания',
        'plural' => 'Транспортные компании',
    ],

    'form' => [
        'section' => [
            'identification' => 'Идентификация',
            'verification' => 'Верификация',
            'verification_description' => 'Компания загружает документы в своём панели (/transport/transporter-documents). Проверьте и подтвердите или отклоните с комментарием.',
            'subscription' => 'Подписка',
        ],
        'label' => [
            'tax_id' => 'ИНН / VAT',
            'verification_status' => 'Статус',
            'verified_at' => 'Подтверждено',
            'verification_notes' => 'Комментарии / причина',
            'rejection_reason' => 'Причина отклонения',
            'plan' => 'Тариф',
        ],
        'helper' => [
            'verification_status' => 'Изменяется только действиями «Подтвердить» / «Отклонить».',
            'verification_notes' => 'Видно компании.',
        ],
    ],

    'table' => [
        'column' => [
            'verification' => 'Верификация',
            'plan' => 'Тариф',
            'subscription' => 'Подписка',
            'last_activity_at' => 'Последняя активность',
            'created_at' => 'Создано',
        ],
    ],

    'action' => [
        'verify' => 'Подтвердить аккаунт',
        'reject' => 'Отклонить аккаунт',
    ],

    'notify' => [
        'verified' => 'Аккаунт подтверждён',
        'verified_body' => 'Компания :name активирована. Может отправлять предложения и счета.',
        'rejected' => 'Аккаунт отклонён',
        'rejected_body' => 'Компания :name отклонена. Они получили письмо с причиной.',
    ],
];
