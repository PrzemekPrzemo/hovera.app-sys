<?php

declare(strict_types=1);

return [
    'roles' => [
        'owner' => 'Eigentümer',
        'admin' => 'Admin',
        'manager' => 'Manager',
        'instructor' => 'Reitlehrer',
        'employee' => 'Mitarbeiter',
        'vet' => 'Tierarzt',
        'viewer' => 'Nur Ansicht',
    ],

    'form' => [
        'label' => [
            'email' => 'E-Mail des Benutzers',
            'name' => 'Vor- und Nachname (optional, nur bei neuem Benutzer)',
            'role' => 'Rolle im Reitstall',
            'attach_email' => 'E-Mail',
            'attach_name' => 'Vor- und Nachname (bei neuem Benutzer)',
            'attach_role' => 'Rolle',
            'impersonate_reason' => 'Grund der Impersonation (DSGVO-Audit)',
        ],
        'helper' => [
            'email' => 'Existiert der Benutzer nicht, wird er angelegt und erhält ein generiertes Passwort.',
            'impersonate_reason' => 'Pflichtfeld. Jede Aktion während der Impersonationssitzung wird im audit_log des Reitstalls markiert.',
        ],
    ],

    'table' => [
        'column' => [
            'email' => 'E-Mail',
            'name' => 'Name',
            'role' => 'Rolle',
            'joined_at' => 'Beigetreten',
            'revoked_at' => 'Widerrufen',
        ],
        'filter' => [
            'status_label' => 'Status',
            'status_placeholder' => 'Aktiv und widerrufen',
            'status_true' => 'Nur widerrufen',
            'status_false' => 'Nur aktiv',
        ],
    ],

    'action' => [
        'attach' => [
            'label' => 'Mitglied hinzufügen',
            'success_attached_title' => 'Mitglied hinzugefügt',
            'success_attached_body' => ':email zum Reitstall hinzugefügt.',
            'success_invited_title' => 'Einladung gesendet',
            'success_invited_body' => 'Einladung an :email gesendet. Link läuft am :expires ab.',
        ],
        'revoke' => [
            'label' => 'Zugriff entziehen',
            'success' => 'Zugriff entzogen',
        ],
        'reactivate' => [
            'label' => 'Wiederherstellen',
            'success' => 'Zugriff wiederhergestellt',
        ],
        'impersonate' => [
            'label' => 'Anmelden als',
            'submit' => 'Impersonation starten',
        ],
    ],
];
