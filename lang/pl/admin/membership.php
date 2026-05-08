<?php

declare(strict_types=1);

return [
    'roles' => [
        'owner' => 'Właściciel',
        'admin' => 'Admin',
        'manager' => 'Manager',
        'instructor' => 'Instruktor',
        'employee' => 'Pracownik',
        'vet' => 'Weterynarz',
        'viewer' => 'Tylko podgląd',
    ],

    'form' => [
        'label' => [
            'email' => 'Email użytkownika',
            'name' => 'Imię i nazwisko (opcjonalne, tylko przy nowym użytkowniku)',
            'role' => 'Rola w stajni',
            'attach_email' => 'Email',
            'attach_name' => 'Imię i nazwisko (jeśli nowy użytkownik)',
            'attach_role' => 'Rola',
            'impersonate_reason' => 'Powód impersonacji (audit RODO)',
        ],
        'helper' => [
            'email' => 'Jeśli użytkownik nie istnieje, zostanie utworzony i otrzyma wygenerowane hasło.',
            'impersonate_reason' => 'Pole wymagane. Każda akcja w trakcie sesji impersonacji jest tagowana w audit_log stajni.',
        ],
    ],

    'table' => [
        'column' => [
            'email' => 'Email',
            'name' => 'Imię',
            'role' => 'Rola',
            'joined_at' => 'Dołączył',
            'revoked_at' => 'Cofnięto',
        ],
        'filter' => [
            'status_label' => 'Status',
            'status_placeholder' => 'Aktywne i cofnięte',
            'status_true' => 'Tylko cofnięte',
            'status_false' => 'Tylko aktywne',
        ],
    ],

    'action' => [
        'attach' => [
            'label' => 'Dodaj członka',
            'success_attached_title' => 'Członek dodany',
            'success_attached_body' => 'Dodano :email do stajni.',
            'success_invited_title' => 'Zaproszenie wysłane',
            'success_invited_body' => 'Wysłano zaproszenie do :email. Link wygasa :expires.',
        ],
        'revoke' => [
            'label' => 'Cofnij dostęp',
            'success' => 'Dostęp cofnięty',
        ],
        'reactivate' => [
            'label' => 'Przywróć',
            'success' => 'Dostęp przywrócony',
        ],
        'impersonate' => [
            'label' => 'Zaloguj jako',
            'submit' => 'Rozpocznij impersonację',
        ],
    ],
];
