<?php

declare(strict_types=1);

return [
    'navigation' => 'Właściciele koni',
    'navigation_group' => 'Tenanci',

    'model' => [
        'singular' => 'Właściciel konia',
        'plural' => 'Właściciele koni',
    ],

    'form' => [
        'section' => [
            'identification' => 'Identyfikacja',
            'owner_account' => 'Konto właściciela',
            'owner_account_description' => 'Dane konta użytkownika powiązanego z tym tenantem. Email pobierany z `User` w bazie central (przez `memberships` z role=owner).',
            'metadata' => 'Metadane (read-only)',
        ],
        'label' => [
            'name' => 'Nazwa',
            'slug' => 'Slug (auto-wygenerowany)',
            'status' => 'Status',
            'terms_accepted_at' => 'Akceptacja regulaminu',
            'owner_email' => 'Email właściciela',
            'owner_phone' => 'Telefon',
            'country' => 'Kraj',
            'locale' => 'Język',
            'timezone' => 'Strefa czasowa',
            'created_at' => 'Data rejestracji',
        ],
        'helper' => [
            'slug' => 'Wygenerowany automatycznie z email\'a przy rejestracji. Niezmienialny.',
        ],
        'option' => [
            'status' => [
                'active' => 'Aktywny',
                'suspended' => 'Zawieszony',
            ],
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Nazwa',
            'owner_email' => 'Email właściciela',
            'status' => 'Status',
            'slug' => 'Slug',
            'created_at' => 'Zarejestrowany',
        ],
    ],

    'action' => [
        'force_password_reset' => [
            'label' => 'Wyślij reset hasła',
            'modal_description' => 'Wyślij email z linkiem reset hasła do :email. Link ważny 60 minut.',
            'no_owner' => 'Brak właściciela konta — nie ma do kogo wysłać reset hasła',
            'success' => 'Email wysłany',
            'success_body' => 'Link do resetu hasła wysłany na :email.',
            'failed' => 'Wysyłka nie powiodła się',
        ],
        'login_as_owner' => [
            'label' => 'Zaloguj jako właściciel',
            'reason_label' => 'Powód (logged w audyt master admina)',
            'reason_helper' => 'Krótki opis dlaczego musisz przejąć kontrolę — np. "user reportował błąd w panelu, sprawdzam", "support ticket #123".',
            'no_user_title' => 'Brak użytkownika',
            'no_user_body' => 'Ten tenant nie ma jeszcze żadnego User\'a w memberships. Sprawdź czy rejestracja zakończyła się sukcesem.',
            'submit' => 'Wejdź w panel',
        ],
    ],
];
