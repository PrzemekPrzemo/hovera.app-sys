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
        'feature' => 'Oznacz jako Polecany',
        'unfeature' => 'Cofnij Polecany',
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
        'featured' => 'Oznaczono jako Polecany',
        'unfeatured' => 'Cofnięto status Polecany',
    ],

    // Sekcja Dokumenty — relation manager w master adminie.
    'documents' => [
        'title' => 'Dokumenty weryfikacyjne',
        'column' => [
            'type' => 'Typ dokumentu',
            'status' => 'Status',
            'filename' => 'Plik',
            'uploaded_at' => 'Wgrany',
            'public' => 'Publicznie',
        ],
        'action' => [
            'preview' => 'Podgląd',
            'download' => 'Pobierz',
            'upload_anonymized' => 'Wgraj zanonimizowaną wersję',
            'remove_anonymized' => 'Usuń wersję publiczną',
        ],
        'upload_anonymized' => [
            'modal_description' => 'Plik bez danych osobowych pojawi się na publicznym profilu firmy /t/{slug} jako potwierdzenie posiadanego dokumentu. Nie wgrywaj oryginału.',
            'file_label' => 'Zanonimizowany dokument (PDF / JPG / PNG, max 5 MB)',
            'helper' => 'Z oryginału usuń: PESEL, adres zamieszkania, podpisy, numery seryjne. Zachowaj: typ dokumentu, datę ważności, organ wydający.',
        ],
        'remove_anonymized' => [
            'modal_description' => 'Plik publiczny zostanie skasowany. Dokument zniknie z publicznego profilu /t/{slug}.',
        ],
        'public' => [
            'yes_tooltip' => 'Widoczny na publicznym profilu firmy.',
            'no_tooltip' => 'Brak wersji zanonimizowanej — niewidoczny publicznie.',
        ],
        'notify' => [
            'public_uploaded' => 'Wersja publiczna wgrana — widoczna na /t/{slug}.',
            'public_removed' => 'Wersja publiczna usunięta.',
        ],
        'missing_table_title' => 'Baza tenanta wymaga migracji',
        'missing_table_body' => 'Tabela "transporter_documents" nie istnieje w bazie :db. To znaczy że ten tenant został sprovisionowany przed wprowadzeniem dokumentów weryfikacyjnych. Aby naprawić uruchom: `php artisan migrate --path=database/migrations/tenant --database=tenant` w kontekście tego tenant\'a. Tymczasowo pokazujemy pustą listę dokumentów.',
    ],
];
