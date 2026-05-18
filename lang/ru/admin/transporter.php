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
            'verified_at' => 'Подтверждено',
            'created_at' => 'Создано',
        ],
    ],

    'action' => [
        'verify' => 'Подтвердить аккаунт',
        'reject' => 'Отклонить аккаунт',
        'login_as_owner' => [
            'label' => 'Войти как транспортер',
            'reason_label' => 'Причина импersonation (GDPR audit)',
            'reason_helper' => 'Обязательно. Сессия записывается в impersonation_sessions + audit_log_master.',
            'submit' => 'Начать impersonation',
            'no_user_title' => 'Нет активного пользователя для этой компании',
            'no_user_body' => 'Сначала добавьте члена команды или пригласите владельца.',
        ],
    ],

    'notify' => [
        'verified' => 'Аккаунт подтверждён',
        'verified_body' => 'Компания :name активирована. Может отправлять предложения и счета.',
        'rejected' => 'Аккаунт отклонён',
        'rejected_body' => 'Компания :name отклонена. Они получили письмо с причиной.',
    ],
];
