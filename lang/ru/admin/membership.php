<?php

declare(strict_types=1);

return [
    'roles' => [
        'owner' => 'Владелец',
        'admin' => 'Администратор',
        'manager' => 'Менеджер',
        'instructor' => 'Инструктор',
        'employee' => 'Сотрудник',
        'vet' => 'Ветеринар',
        'viewer' => 'Только просмотр',
    ],

    'form' => [
        'label' => [
            'email' => 'Email пользователя',
            'name' => 'Имя и фамилия (опционально, только для нового пользователя)',
            'role' => 'Роль в конюшне',
            'attach_email' => 'Email',
            'attach_name' => 'Имя и фамилия (если новый пользователь)',
            'attach_role' => 'Роль',
            'impersonate_reason' => 'Причина имперсонации (аудит GDPR)',
        ],
        'helper' => [
            'email' => 'Если пользователь не существует, он будет создан и получит сгенерированный пароль.',
            'impersonate_reason' => 'Поле обязательно. Каждое действие во время сессии имперсонации помечается в audit_log конюшни.',
        ],
    ],

    'table' => [
        'column' => [
            'email' => 'Email',
            'name' => 'Имя',
            'role' => 'Роль',
            'joined_at' => 'Присоединился',
            'revoked_at' => 'Отозван',
        ],
        'filter' => [
            'status_label' => 'Статус',
            'status_placeholder' => 'Активные и отозванные',
            'status_true' => 'Только отозванные',
            'status_false' => 'Только активные',
        ],
    ],

    'action' => [
        'attach' => [
            'label' => 'Добавить участника',
            'success_attached_title' => 'Участник добавлен',
            'success_attached_body' => 'Добавлен :email в конюшню.',
            'success_invited_title' => 'Приглашение отправлено',
            'success_invited_body' => 'Отправлено приглашение на :email. Ссылка истекает :expires.',
        ],
        'revoke' => [
            'label' => 'Отозвать доступ',
            'success' => 'Доступ отозван',
        ],
        'reactivate' => [
            'label' => 'Восстановить',
            'success' => 'Доступ восстановлен',
        ],
        'impersonate' => [
            'label' => 'Войти как',
            'submit' => 'Начать имперсонацию',
        ],
    ],
];
