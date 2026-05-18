<?php

declare(strict_types=1);

return [
    'navigation' => 'Transportunternehmen',

    'model' => [
        'singular' => 'Transportunternehmen',
        'plural' => 'Transportunternehmen',
    ],

    'form' => [
        'section' => [
            'identification' => 'Identifikation',
            'verification' => 'Verifizierung',
            'verification_description' => 'Das Unternehmen lädt Dokumente im eigenen Panel hoch (/transport/transporter-documents). Prüfen und genehmigen oder ablehnen.',
            'subscription' => 'Abonnement',
        ],
        'label' => [
            'tax_id' => 'USt-ID',
            'verification_status' => 'Status',
            'verified_at' => 'Verifiziert am',
            'verification_notes' => 'Anmerkungen / Begründung',
            'rejection_reason' => 'Ablehnungsgrund',
            'plan' => 'Tarif',
        ],
        'helper' => [
            'verification_status' => 'Nur durch Aktionen „Genehmigen" / „Ablehnen" änderbar.',
            'verification_notes' => 'Sichtbar für das Unternehmen.',
        ],
    ],

    'table' => [
        'column' => [
            'verification' => 'Verifizierung',
            'plan' => 'Tarif',
            'subscription' => 'Abonnement',
            'last_activity_at' => 'Letzte Aktivität',
            'verified_at' => 'Verifiziert am',
            'created_at' => 'Erstellt',
        ],
    ],

    'action' => [
        'verify' => 'Konto genehmigen',
        'reject' => 'Konto ablehnen',
        'login_as_owner' => [
            'label' => 'Als Transporteur anmelden',
            'reason_label' => 'Grund für Impersonation (DSGVO-Audit)',
            'reason_helper' => 'Pflichtfeld. Sitzung wird in impersonation_sessions + audit_log_master gespeichert.',
            'submit' => 'Impersonation starten',
            'no_user_title' => 'Kein aktiver Benutzer für dieses Unternehmen',
            'no_user_body' => 'Fügen Sie zuerst ein Teammitglied hinzu oder laden Sie einen Owner ein.',
        ],
    ],

    'notify' => [
        'verified' => 'Konto genehmigt',
        'verified_body' => 'Unternehmen :name aktiviert. Es kann Angebote und Rechnungen versenden.',
        'rejected' => 'Konto abgelehnt',
        'rejected_body' => 'Unternehmen :name abgelehnt. Es erhielt eine E-Mail mit der Begründung.',
    ],
];
