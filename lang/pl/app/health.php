<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'entry' => 'Wpis',
            'details' => 'Szczegóły',
        ],
        'label' => [
            'horse' => 'Koń',
            'horse_identification' => 'Identyfikacja konia',
            'template' => 'Szablon zabiegu',
            'template_placeholder' => '— opcjonalnie wybierz szablon —',
            'type' => 'Typ',
            'performed_at' => 'Data zabiegu',
            'performed_by' => 'Wykonał (lekarz / kowal / firma)',
            'performed_by_placeholder' => 'np. asystent kowala (jeśli inna osoba niż wybrana wyżej)',
            'specialist' => 'Specjalista',
            'specialist_placeholder' => '— wybierz z listy specjalistów —',
            'summary' => 'Krótki opis',
            'summary_placeholder' => 'Szczepienie tężec + grypa',
            'next_due_at' => 'Następny zabieg',
            'cost' => 'Koszt',
            'details' => 'Notatki / leki / zalecenia',
        ],
        'helper' => [
            'template' => 'Wybór szablonu wypełni typ, opis i sugerowany termin następnej wizyty.',
            'next_due_at' => 'Dzięki temu pojawi się alert na dashboardzie.',
            'specialist' => 'Lista filtrowana wg typu wpisu — kowale dla "Kowal", weterynarze dla pozostałych typów. Skonfiguruj listę w Stajnia → Specjaliści.',
        ],
        'horse_identification' => [
            'microchip' => 'Mikrochip',
            'passport' => 'Numer paszportu',
            'ueln' => 'UELN',
            'empty_warning' => 'Brak danych identyfikacyjnych dla tego konia. Uzupełnij mikrochip / paszport w karcie konia przed zabiegiem.',
            'missing' => 'Wybrany koń nie istnieje (rekord usunięty).',
        ],
    ],

    'table' => [
        'column' => [
            'performed_at' => 'Data',
            'horse' => 'Koń',
            'type' => 'Typ',
            'summary' => 'Opis',
            'performed_by' => 'Wykonał',
            'next_due_at' => 'Następny',
            'cost' => 'Koszt',
        ],
        'filter' => [
            'horse' => 'Koń',
            'overdue' => 'Przeterminowane (next due in past)',
            'due_30' => 'Następny w 30 dni',
        ],
    ],

    'bulk' => [
        'batch_complete' => [
            'label' => 'Zarejestruj wykonanie (zbiorczo)',
            'modal_heading' => 'Wpisz wspólne dane dla wszystkich wybranych zabiegów',
            'modal_description' => 'Dla każdego wybranego wpisu utworzymy nowy follow-up HealthRecord z poniższymi polami (data, opis, specjalista, następny termin, koszt). Stare wpisy pozostaną nienaruszone — to nowa historia wykonania.',
            'next_due_helper' => 'Wspólny dla wszystkich koni. Opcjonalny — możesz zostawić puste i ustawić osobno potem.',
            'cost_per_horse' => 'Koszt jednostkowy (per koń)',
            'success_title' => 'Zarejestrowano wykonanie zbiorczo',
            'success_body' => 'Utworzono :count nowych wpisów zdrowotnych. Lista pokazuje teraz wpisy dla wszystkich koni.',
        ],
    ],
];
