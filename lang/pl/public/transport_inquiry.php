<?php

declare(strict_types=1);

return [
    'title' => 'Zapytanie o transport koni',
    'heading' => 'Zapytanie o transport koni',
    'subtitle' => 'Wypełnij formularz — wyślemy zapytanie do zweryfikowanych firm transportowych w Twoim regionie. Otrzymasz oferty mailem.',
    'errors_heading' => 'Sprawdź formularz:',

    'direct_target_banner' => 'Wysyłasz zapytanie bezpośrednio do :name. Otrzymasz odpowiedź tylko od tej firmy.',
    'direct_target_switch_to_broadcast' => 'Wolę wysłać do wszystkich pasujących przewoźników',

    'originator_banner' => [
        'from_stable' => 'Zlecenie z poziomu stajni :name',
        'back_to_app' => 'wróć do panelu',
    ],

    'prefill' => [
        'horse_note' => 'Koń: :name',
    ],

    'label' => [
        'customer_name' => 'Imię i nazwisko',
        'customer_email' => 'E-mail',
        'customer_phone' => 'Telefon (opcjonalnie)',
        'pickup_address' => 'Skąd (adres odbioru)',
        'dropoff_address' => 'Dokąd (adres dostarczenia)',
        'preferred_date' => 'Preferowana data',
        'preferred_time' => 'Godzina (opcjonalnie)',
        'flexible_date' => 'Data jest elastyczna (±2 dni OK)',
        'horse_count' => 'Liczba koni',
        'notes' => 'Dodatkowe informacje',
        'client_for' => 'Klient zlecenia',
        'terms' => 'Wyrażam zgodę na przekazanie moich danych zweryfikowanym przewoźnikom w celu przygotowania ofert. <a href="/polityka-prywatnosci" target="_blank">Polityka prywatności</a>.',
    ],

    'client_for' => [
        'stable' => 'Stajnia (ja)',
        'boarder_prefix' => 'Boarder: ',
        'helper' => 'Wybierz boarder\'a, jeśli to transport organizowany dla pensjonariusza — faktura po akceptacji oferty trafi do właściciela konia, nie do stajni.',
    ],

    'boarder' => [
        'unknown_horse' => '(koń nieznany)',
        'unknown_owner' => '(właściciel nieznany)',
    ],

    'placeholder' => [
        'pickup_address' => 'np. Stajnia Pegaz, ul. Łąkowa 1, Warszawa',
        'dropoff_address' => 'np. Olsztyn, ul. Konna 5',
        'notes' => 'Np. konie hodowlane, wymagane prawa transportu zwierząt, ubezpieczenie OCS...',
    ],

    'action' => [
        'submit' => 'Wyślij zapytanie',
    ],

    'error' => [
        'geocoding' => 'Nie udało się znaleźć podanego adresu: :msg. Spróbuj wpisać miasto + ulicę.',
        'terms' => 'Musisz wyrazić zgodę na przekazanie danych przewoźnikom.',
    ],

    'thanks_title' => 'Zapytanie przyjęte',
    'thanks_heading' => 'Dziękujemy!',
    'thanks_body' => 'Wysłaliśmy Twoje zapytanie do firm transportowych. Oferty dostaniesz mailem na :email w ciągu 24 godzin.',
    'thanks_reference' => 'Numer referencyjny',

    // Disclaimer dot. roli Hovera (pośrednik, nie przewoźnik) — wymagane przez
    // regulamin marketplace transportowego /regulamin-marketplace.
    'disclaimer_intermediary' => 'Hovera jest pośrednikiem marketplace — nie jest przewoźnikiem i nie wykonuje transportów. Umowa transportu zawierana jest BEZPOŚREDNIO między Tobą a wybranym przewoźnikiem po akceptacji jego oferty. Szczegóły w <a href="/regulamin-marketplace" target="_blank">regulaminie marketplace transportowego</a>.',
    'disclaimer_intermediary_thanks' => 'Wybrany przewoźnik skontaktuje się z Tobą bezpośrednio — to z nim zawrzesz umowę przewozu. Hovera jest jedynie technologicznym pośrednikiem, nie jest stroną umowy ani nie odpowiada za realizację transportu. Szczegóły: <a href="/regulamin-marketplace" target="_blank">regulamin marketplace</a>.',
];
