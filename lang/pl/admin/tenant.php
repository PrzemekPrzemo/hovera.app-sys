<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'identification' => 'Identyfikacja',
            'location' => 'Lokalizacja',
            'subscription' => 'Subskrypcja',
            'branding' => 'Branding',
            'branding_description' => 'Używane na publicznej stronie /s/{slug} i w mailach.',
            'public_profile' => 'Profil publiczny',
            'public_profile_description' => 'Dane wyświetlane na publicznej stronie stajni /s/{slug}.',
            'database' => 'Baza danych',
        ],
        'label' => [
            'type' => 'Typ tenanta',
            'tax_id' => 'NIP / VAT ID',
            'plan' => 'Plan',
            'primary_color' => 'Kolor wiodący',
            'logo_url' => 'URL logo',
            'public_description' => 'Opis stajni',
            'public_email' => 'Email kontaktowy (publiczny)',
            'public_phone' => 'Telefon kontaktowy',
            'public_address' => 'Adres',
            'public_website' => 'Strona WWW',
        ],
        'option' => [
            'type' => [
                'stable' => 'Stajnia jeździecka',
                'transporter' => 'Firma transportowa',
                'horse_owner' => 'Właściciel konia',
            ],
        ],
        'helper' => [
            'slug' => 'Niezmienne. Używane w adresach i nazwie bazy.',
            'type' => 'Określa panel po zalogowaniu (Stajnia → /app, Transport → /transport) i listę dostępnych planów. Po utworzeniu niezmienialny.',
            'plan' => 'Lista planów filtrowana wybranym typem tenanta.',
        ],
        'lookup' => [
            'action_label' => 'Pobierz z GUS/CEIDG/KRS',
            'invalid_nip' => 'Niepoprawny NIP — sprawdź sumę kontrolną.',
            'not_found' => 'Nie znaleziono firmy. Sprawdź NIP albo wprowadź dane ręcznie.',
            'success' => 'Dane pobrane z rejestrów państwowych.',
            'success_sources' => 'Źródła: :sources',
        ],
    ],

    'notify' => [
        'created_stable' => 'Stajnia utworzona',
        'created_transporter' => 'Firma transportowa utworzona',
        'created_body' => 'Baza :db została zainicjowana.',
        'create_failed' => 'Tworzenie tenanta nie powiodło się',
    ],

    'table' => [
        'column' => [
            'type' => 'Typ',
            'country' => 'Kraj',
            'plan' => 'Plan',
            'db_name' => 'Baza',
            'created_at' => 'Utworzona',
        ],
        'filter' => [
            'type' => 'Typ tenanta',
        ],
    ],

    'action' => [
        'suspend' => [
            'label' => 'Zawieś',
            'notification_title' => 'Stajnia zawieszona',
        ],
        'reactivate' => [
            'label' => 'Aktywuj ponownie',
            'notification_title' => 'Stajnia ponownie aktywna',
        ],
        'soft_delete' => [
            'label' => 'Soft delete',
        ],
        'login_as_owner' => [
            'label' => 'Zaloguj jako stajnia',
            'reason_label' => 'Powód impersonacji (audit RODO)',
            'reason_helper' => 'Wymagane. Sesja jest wpisana do impersonation_sessions + audit_log_master.',
            'submit' => 'Rozpocznij impersonację',
            'no_user_title' => 'Brak aktywnego usera dla tej stajni',
            'no_user_body' => 'Najpierw dodaj członka zespołu lub zaproś ownera.',
        ],
        'seed_demo' => [
            'label' => 'Wgraj demo dane',
            'modal_heading' => 'Wgrać demo dane do :name?',
            'modal_description' => 'Doda 14 koni, 6 klientów, 12 boxów, kalendarz, faktury i resztę zestawu pokazowego. Działa na bazie tenanta.',
            'fresh_label' => 'Wyczyść istniejące dane (DROP all tables)',
            'fresh_helper' => 'UWAGA: usunie wszystkie obecne dane stajni przed seed.',
            'success_title' => 'Demo dane wgrane',
            'success_body' => 'Stajnia :name ma teraz pełen zestaw pokazowy.',
            'failure_title' => 'Nie udało się wgrać demo',
        ],
        'destroy' => [
            'label' => 'Drop database',
            'modal_heading' => 'Trwale usuń stajnię',
            'modal_description' => 'Tej operacji NIE można cofnąć. Bazy :db oraz konto MySQL :user zostaną usunięte fizycznie.',
            'confirm_slug_label' => 'Wpisz slug stajni, aby potwierdzić',
            'slug_mismatch' => 'Slug się nie zgadza.',
            'success_title' => 'Stajnia trwale usunięta',
        ],
    ],
];
