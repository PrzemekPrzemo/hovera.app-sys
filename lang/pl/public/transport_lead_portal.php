<?php

declare(strict_types=1);

return [
    'title' => 'Twoje zapytanie transportowe',
    'heading' => 'Twoje zapytanie transportowe',
    'subtitle' => 'Tu zobaczysz wszystkie oferty przewoźników na to konkretne zapytanie. '
        .'Zachowaj ten link — działa permanentnie. Aby śledzić historię wszystkich Twoich '
        .'zapytań, załóż konto (sekcja niżej).',

    'section' => [
        'summary' => 'Twoje zapytanie',
        'offers' => 'Oferty przewoźników (:count)',
    ],

    'label' => [
        'pickup' => 'Odbiór',
        'dropoff' => 'Dostawa',
        'date' => 'Preferowana data',
        'horses' => 'Liczba koni',
        'status' => 'Status',
        'notes' => 'Uwagi',
    ],

    'status' => [
        'open' => 'Otwarte — czekamy na oferty',
        'quoted' => 'Otrzymano oferty',
        'accepted' => 'Oferta przyjęta',
        'expired' => 'Wygasło',
        'cancelled' => 'Anulowane',
    ],

    'response' => [
        'accepted' => 'Oferta zaakceptowana',
        'proposed_date' => 'Proponowana data',
    ],

    'no_responses' => 'Jeszcze brak ofert. Powiadomimy Cię mailem gdy przewoźnik odpowie.',
    'transporter_unknown' => 'Przewoźnik (nazwa wczyta się wkrótce)',

    'signup' => [
        'heading' => '🎁 Załóż konto żeby widzieć całą historię',
        'body' => 'Dzięki kontu zobaczysz historię wszystkich swoich zapytań w jednym miejscu, '
            .'dostaniesz powiadomienia push i będziesz mógł szybciej składać kolejne zapytania '
            .'(formularz wstępnie wypełniony).',
        'cta' => 'Załóż konto teraz',
        'already_logged_in' => 'Jesteś zalogowany na to konto.',
        'view_history' => 'Zobacz historię moich zapytań →',
        'account_exists' => 'Konto z tym adresem email już istnieje. Zaloguj się aby zobaczyć historię.',
        'login_cta' => 'Zaloguj się',
    ],

    'signup_form' => [
        'title' => 'Załóż konto klienta hovera',
        'heading' => 'Załóż konto żeby widzieć historię zapytań',
        'intro' => 'Tworzymy konto powiązane z Twoim adresem email (:email). Po zalogowaniu '
            .'zobaczysz historię wszystkich swoich zapytań transportowych w jednym miejscu.',
        'label' => [
            'email' => 'Email',
            'password' => 'Hasło',
            'password_confirmation' => 'Powtórz hasło',
            'terms' => 'Akceptuję regulamin oraz politykę prywatności hovera.app',
        ],
        'hint' => [
            'password' => 'Min. 8 znaków.',
            'email_locked' => 'Adres email jest na stałe powiązany z Twoim zapytaniem — nie można go zmienić.',
        ],
        'submit' => 'Utwórz konto i zaloguj',
        'cancel' => 'Wróć do portalu',
        'created' => 'Konto utworzone — jesteś zalogowany. Tu znajdziesz historię swoich zapytań.',
        'errors' => [
            'terms' => 'Musisz zaakceptować regulamin żeby założyć konto.',
            'honeypot' => 'Wykryto bot — spróbuj ponownie.',
        ],
    ],

    'my_inquiries' => [
        'title' => 'Moje zapytania transportowe',
        'heading' => 'Moje zapytania transportowe',
        'empty' => 'Nie masz jeszcze żadnych zapytań. Wypełnij formularz aby utworzyć pierwsze.',
        'empty_cta' => 'Nowe zapytanie',
        'column' => [
            'date' => 'Data zapytania',
            'route' => 'Trasa',
            'preferred_date' => 'Preferowana data',
            'horses' => 'Konie',
            'status' => 'Status',
            'offers' => 'Oferty',
        ],
        'view_link' => 'Otwórz portal',
        'logout' => 'Wyloguj',
    ],

    'footer' => [
        'permanent_link' => 'Ten link działa bezterminowo. Zachowaj go w bezpiecznym miejscu.',
        'disclaimer_intermediary' => 'Hovera to platforma marketplace — nie jesteśmy przewoźnikiem. '
            .'Umowę przewozu zawierasz bezpośrednio z wybranym przewoźnikiem.',
    ],
];
