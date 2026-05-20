<?php

declare(strict_types=1);

return [
    'navigation' => 'Kalkulator wyceny',
    'title' => 'Kalkulator wyceny transportu',

    'section' => [
        'route' => 'Trasa',
        'options' => 'Opcje',
    ],

    'form' => [
        'label' => [
            'from_address' => 'Adres odbioru',
            'to_address' => 'Adres dostarczenia',
            'loaded' => 'Z koniem (z ładunkiem)',
            'round_trip' => 'Trasa z powrotem',
            'mode' => 'Tryb kalkulacji',
            'avoid_tolls' => 'Omijaj autostrady płatne',
            'avoid_ferries' => 'Omijaj promy',
            'profile' => 'Profil pojazdu',
            'horses_count' => 'Liczba koni',
        ],
        'helper' => [
            'mode' => '„Powrót do bazy" doliczy km z punktu docelowego do bazy transportera. Wymaga ustawionej bazy w Ustawieniach Transportu — bez tego spada do trybu „w dwie strony".',
            'horses_count' => 'Doliczenie naliczone od drugiego konia w górę, zgodnie ze stawką w Ustawieniach Transportu.',
        ],
        'placeholder' => [
            'from_address' => 'np. Stajnia Marymoncka 1, Warszawa',
            'to_address' => 'np. Olsztyn, ul. Sportowa 1',
        ],
        'option' => [
            'profile' => [
                'truck' => 'Ciężarowy (HGV)',
                'car' => 'Osobowy',
            ],
        ],
    ],

    'action' => [
        'submit' => 'Policz wycenę',
        'calculated' => 'Wycena obliczona.',
        'failed' => 'Nie udało się obliczyć wyceny',
        'save_as_quote' => 'Zapisz jako ofertę',
    ],

    'notify' => [
        'lead_prefilled_title' => 'Dane z zapytania zaciągnięte',
        'lead_prefilled_body' => 'Adresy i kontakt klienta są już wypełnione — kliknij "Policz wycenę", aby obliczyć cenę.',
    ],

    'result' => [
        'heading' => 'Wynik wyceny',
        'from' => 'Skąd',
        'to' => 'Dokąd',
        'distance' => 'Dystans',
        'duration' => 'Czas przejazdu',
        'rate_used' => 'Zastosowana stawka',
        'base_cost' => 'Koszt podstawowy',
        'fuel_surcharge' => 'Dopłata paliwowa',
        'extra_horse_fee' => 'Dodatkowe konie: :count × :rate :currency',
        'minimum_adjustment' => 'Dobór do opłaty minimalnej',
        'net_total' => 'Razem netto',
        'vat' => 'VAT (:rate%)',
        'gross_total' => 'Razem brutto',
        'routing_via' => 'Trasa wyliczona przez: :provider',
    ],

    'error' => [
        'no_tenant' => 'Brak aktywnego tenanta — zaloguj się ponownie.',
        'unknown' => 'Wystąpił nieoczekiwany błąd. Spróbuj ponownie.',
    ],
];
