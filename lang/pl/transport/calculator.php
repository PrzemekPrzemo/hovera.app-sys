<?php

declare(strict_types=1);

return [
    'navigation' => 'Kalkulator wyceny',
    'title' => 'Kalkulator wyceny transportu',

    'section' => [
        'route' => 'Trasa',
        'options' => 'Opcje',
        'extra_costs' => 'Dodatkowe opłaty i marża',
        'extra_costs_description' => 'Stałe opłaty (autostrady, prom etc.) + marża procentowa per wycena. Puste = wartości domyślne z Ustawień Transportu.',
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
            'fixed_fees' => 'Stałe opłaty (autostrady, prom, etc.)',
            'fixed_fees_name' => 'Nazwa',
            'fixed_fees_amount' => 'Kwota',
            'surcharge_percent' => 'Marża %',
        ],
        'helper' => [
            'mode' => '„Powrót do bazy" doliczy km z punktu docelowego do bazy transportera. Wymaga ustawionej bazy w Ustawieniach Transportu — bez tego spada do trybu „w dwie strony".',
            'horses_count' => 'Doliczenie naliczone od drugiego konia w górę, zgodnie ze stawką w Ustawieniach Transportu.',
            'fixed_fees' => 'Każda pozycja zostanie doliczona do wyceny. Wartości domyślne — z Ustawień Transportu (jeśli pole zostawisz puste).',
            'surcharge_percent' => 'Marża doliczana procentowo do sumy kosztów (po dopasowaniu do opłaty minimalnej, przed VAT). Pusto = wartość domyślna z Ustawień. 0 = brak marży.',
        ],
        'action' => [
            'add_fixed_fee' => 'Dodaj opłatę',
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
        'save_as_quote_inline' => 'Zapisz i przejdź do edycji',
        'saved_as_quote_inline_title' => 'Oferta utworzona',
        'saved_as_quote_inline_body' => 'Oferta :number została zapisana. Uzupełnij klienta i dane kontraktu, aby ją wysłać.',
        'saved_as_quote_inline_placeholder_customer' => '(do uzupełnienia)',
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
        'surcharge' => 'Marża (:percent%)',
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

    'live' => [
        'title' => 'Podgląd na żywo',
        'hint' => 'Aktualizuje się automatycznie podczas edycji',
        'loading' => 'Liczenie…',
        'missing' => 'Uzupełnij adresy, aby zobaczyć podgląd ceny',
        'error' => 'Nie udało się odświeżyć podglądu.',
        'currency_fallback' => 'PLN',
        'extra_horses' => 'Dodatkowe konie (:count)',
        'surcharge' => 'Marża (:percent%)',
        'vat' => 'VAT (:rate%)',
        'expand' => 'Pokaż szczegóły',
        'collapse' => 'Schowaj szczegóły',
    ],
];
