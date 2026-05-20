<?php

declare(strict_types=1);

return [
    'order' => [
        'navigation' => 'Zamów transport',
        'title' => 'Zamów transport',
        'heading' => 'Nowe zamówienie transportu',

        'section' => [
            'horse' => 'Koń',
            'route' => 'Trasa i termin',
            'notes' => 'Uwagi dla przewoźnika',
        ],

        'label' => [
            'horse' => 'Wybierz konia',
            'pickup' => 'Adres odbioru',
            'dropoff' => 'Adres dostarczenia',
            'preferred_date' => 'Preferowany termin',
            'preferred_time' => 'Preferowana godzina',
            'mode' => 'Tryb przewozu',
            'notes' => 'Dodatkowe informacje',
        ],

        'placeholder' => [
            'horse' => 'Bez przypisania (wpiszesz w notatkach)',
            'pickup' => 'np. ul. Stajenna 1, 02-123 Warszawa',
            'dropoff' => 'np. Hipodrom Sopot, Polanki 91',
            'notes' => 'Specjalne potrzeby konia, godziny dostępności, itp.',
        ],

        'helper' => [
            'horse' => 'Możesz najpierw dodać konia w sekcji „Moje konie".',
            'mode' => 'Jedna strona / w obie strony / powrót przewoźnika do bazy.',
        ],

        'action' => [
            'submit' => 'Wyślij zapytanie do przewoźników',
        ],

        'info' => [
            'how_it_works' => 'Twoje zapytanie zostanie wysłane do zweryfikowanych przewoźników działających na trasie. Oferty pojawią się w sekcji „Moje zamówienia" — porównasz ceny i wybierzesz przewoźnika.',
        ],

        'notify' => [
            'success_title' => 'Zapytanie wysłane',
            'success_body' => 'Przewoźnicy z Twojego regionu otrzymali powiadomienie. Oferty zobaczysz tutaj.',
            'failed_title' => 'Nie udało się utworzyć zamówienia',
            'failed_body' => 'Spróbuj ponownie za chwilę. Jeśli problem się powtarza, skontaktuj się z nami.',
            'geocoding_failed_title' => 'Nie rozpoznaliśmy adresu',
        ],
    ],

    'orders' => [
        'navigation' => 'Moje zamówienia',

        'model' => [
            'singular' => 'zamówienie transportu',
            'plural' => 'moje zamówienia',
        ],

        'section' => [
            'route' => 'Trasa',
            'horse' => 'Koń',
            'notes' => 'Uwagi',
            'lifecycle' => 'Status',
            'responses' => 'Oferty od przewoźników',
            'responses_description' => 'Liczba ofert, które otrzymałeś na to zapytanie.',
        ],

        'label' => [
            'pickup' => 'Adres odbioru',
            'dropoff' => 'Adres dostarczenia',
            'preferred_date' => 'Termin',
            'preferred_time' => 'Godzina',
            'mode' => 'Tryb',
            'horse' => 'Koń',
            'status' => 'Status',
            'created_at' => 'Utworzono',
        ],

        'table' => [
            'date' => 'Termin',
            'pickup' => 'Skąd',
            'dropoff' => 'Dokąd',
            'horse' => 'Koń',
            'mode' => 'Tryb',
            'status' => 'Status',
        ],

        'status' => [
            'draft' => 'Szkic',
            'open' => 'Otwarte',
            'quoted' => 'Otrzymano oferty',
            'accepted' => 'Zaakceptowane',
            'expired' => 'Wygasłe',
            'cancelled' => 'Anulowane',
        ],

        'responses' => [
            'none' => 'Jeszcze nie ma ofert. Damy Ci znać, gdy coś się pojawi.',
            'count' => ':count nowych ofert do porównania.',
            'lead_missing' => 'Nie udało się pobrać szczegółów. Skontaktuj się z nami, jeśli problem się powtarza.',
        ],

        'empty' => [
            'heading' => 'Brak zamówień transportu',
            'description' => 'Złóż pierwsze zapytanie — porównaj oferty zweryfikowanych przewoźników.',
            'cta' => 'Zamów transport',
        ],

        'action' => [
            'create' => 'Nowe zamówienie',
        ],
    ],

    'widget' => [
        'upcoming' => [
            'heading' => 'Nadchodzący transport',
            'description' => 'Otwarte i zaakceptowane zamówienia z najbliższego terminu.',
            'empty' => 'Nie masz nadchodzących zamówień transportu.',
            'cta' => 'Zamów transport',
        ],
    ],
];
