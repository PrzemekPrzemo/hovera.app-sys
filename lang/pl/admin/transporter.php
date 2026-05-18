<?php

declare(strict_types=1);

return [
    'navigation' => 'Firmy transportowe',

    'model' => [
        'singular' => 'firma transportowa',
        'plural' => 'Firmy transportowe',
    ],

    'form' => [
        'section' => [
            'identification' => 'Identyfikacja',
            'verification' => 'Weryfikacja',
            'verification_description' => 'Dokumenty wgrywa firma w swoim panelu (/transport/transporter-documents). Po sprawdzeniu zatwierdź lub odrzuć z notatką.',
            'subscription' => 'Subskrypcja',
        ],
        'label' => [
            'tax_id' => 'NIP / VAT ID',
            'verification_status' => 'Status',
            'verified_at' => 'Zweryfikowano',
            'verification_notes' => 'Notatki / powód',
            'rejection_reason' => 'Powód odrzucenia',
            'plan' => 'Plan',
        ],
        'helper' => [
            'verification_status' => 'Zmieniane wyłącznie przez akcje „Zatwierdź" / „Odrzuć".',
            'verification_notes' => 'Widoczne dla firmy transportowej.',
        ],
    ],

    'table' => [
        'column' => [
            'verification' => 'Weryfikacja',
            'plan' => 'Plan',
            'subscription' => 'Subskrypcja',
            'last_activity_at' => 'Ostatnia aktywność',
            'verified_at' => 'Zweryfikowano',
            'created_at' => 'Założono',
        ],
    ],

    'action' => [
        'verify' => 'Zatwierdź konto',
        'reject' => 'Odrzuć konto',
        'login_as_owner' => [
            'label' => 'Zaloguj jako transporter',
            'reason_label' => 'Powód impersonacji (audit RODO)',
            'reason_helper' => 'Wymagane. Sesja jest wpisana do impersonation_sessions + audit_log_master.',
            'submit' => 'Rozpocznij impersonację',
            'no_user_title' => 'Brak aktywnego usera dla tej firmy',
            'no_user_body' => 'Najpierw dodaj członka zespołu lub zaproś ownera.',
        ],
    ],

    'notify' => [
        'verified' => 'Konto zatwierdzone',
        'verified_body' => 'Konto firmy :name aktywowane. Firma może wystawiać oferty i faktury.',
        'rejected' => 'Konto odrzucone',
        'rejected_body' => 'Konto firmy :name odrzucone. Firma otrzymała mail z powodem.',
    ],
];
