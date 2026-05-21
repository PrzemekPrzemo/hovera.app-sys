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
            'favorite_route' => '⭐ Wczytaj ulubioną trasę',
            'favorite_transporters' => '⭐ Wybierz przewoźników (opcjonalnie)',
        ],

        'label' => [
            'horse' => 'Wybierz konia',
            'pickup' => 'Adres odbioru',
            'dropoff' => 'Adres dostarczenia',
            'preferred_date' => 'Preferowany termin',
            'preferred_time' => 'Preferowana godzina',
            'mode' => 'Tryb przewozu',
            'notes' => 'Dodatkowe informacje',
            'favorite_route' => 'Wybierz z zapisanych tras',
            'targeted_mode' => 'Wyślij TYLKO do moich ulubionych przewoźników',
            'favorite_transporters' => 'Wybierz przewoźników do których wyślemy zapytanie',
        ],

        'placeholder' => [
            'horse' => 'Bez przypisania (wpiszesz w notatkach)',
            'pickup' => 'np. ul. Stajenna 1, 02-123 Warszawa',
            'dropoff' => 'np. Hipodrom Sopot, Polanki 91',
            'notes' => 'Specjalne potrzeby konia, godziny dostępności, itp.',
            'favorite_route' => '— wybierz z listy zapisanych tras —',
        ],

        'helper' => [
            'horse' => 'Możesz najpierw dodać konia w sekcji „Moje konie".',
            'mode' => 'Jedna strona / w obie strony / powrót przewoźnika do bazy.',
            'favorite_route' => 'Po wyborze formularz automatycznie wypełni pickup, dropoff i notatki. Dodajesz nowe trasy przez akcję „Zapisz jako ulubiona trasa" po wypełnieniu pól.',
            'favorite_transporters' => 'Domyślnie zapytanie idzie do wszystkich zweryfikowanych przewoźników w Twoim regionie (broadcast). Jeśli zaznaczysz „tylko ulubieni" — leci wyłącznie do wybranej listy. Listę ulubionych edytujesz w „Ulubieni przewoźnicy".',
            'targeted_mode' => 'WYŁĄCZONE = broadcast (więcej ofert). WŁĄCZONE = targeted (tylko zaufani).',
        ],

        // (action key merged below)

        'action' => [
            'submit' => 'Wyślij zapytanie do przewoźników',
            'save_as_favorite' => [
                'label' => 'Zapisz jako ulubiona trasa',
                'label_input' => 'Nazwa trasy',
                'placeholder' => 'np. „Klinika koni Janów Podlaski"',
                'missing_addresses' => 'Najpierw wypełnij adres odbioru i dostarczenia.',
                'success' => 'Trasa „:label" zapisana — wybierzesz ją z dropdown\'u przy następnym zamówieniu.',
            ],
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

    'notifications' => [
        'new_offers' => 'Nowe oferty',
        'new_offers_description' => 'Łącznie odpowiedzi przewoźników na Twoje zlecenia',
        'accepted' => 'Zaakceptowane',
        'accepted_description' => 'Z ostatnich 14 dni',
        'upcoming' => 'W ciągu 3 dni',
        'upcoming_description' => 'Transport zaplanowany na najbliższe dni',
    ],
];
