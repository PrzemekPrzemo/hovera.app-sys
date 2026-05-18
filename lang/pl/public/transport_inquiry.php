<?php

declare(strict_types=1);

return [
    'title' => 'Zapytanie o transport koni',
    'heading' => 'Zapytanie o transport koni',
    'subtitle' => 'Wypełnij formularz — wyślemy zapytanie do zweryfikowanych firm transportowych w Twoim regionie. Otrzymasz oferty mailem.',
    'errors_heading' => 'Sprawdź formularz:',

    'direct_target_banner' => 'Wysyłasz zapytanie bezpośrednio do :name. Otrzymasz odpowiedź tylko od tej firmy.',
    'direct_target_switch_to_broadcast' => 'Wolę wysłać do wszystkich pasujących przewoźników',

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
        'terms' => 'Wyrażam zgodę na przekazanie moich danych zweryfikowanym przewoźnikom w celu przygotowania ofert. <a href="/polityka-prywatnosci" target="_blank">Polityka prywatności</a>.',
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
];
