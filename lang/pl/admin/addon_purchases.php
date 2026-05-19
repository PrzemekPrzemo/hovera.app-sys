<?php

declare(strict_types=1);

return [
    'navigation' => 'Zakupy add-onów',
    'model' => 'Zakup add-onu',
    'model_plural' => 'Zakupy add-onów',

    'form' => [
        'section' => [
            'basics' => 'Podstawowe informacje',
            'status' => 'Status i płatność',
        ],
        'label' => [
            'tenant' => 'Stajnia (tenant)',
            'addon' => 'Add-on (wybierz z katalogu)',
            'addon_code' => 'Kod add-onu',
            'addon_name' => 'Nazwa add-onu (snapshot)',
            'currency' => 'Waluta',
            'amount_cents' => 'Kwota (grosze)',
            'status' => 'Status',
            'p24_link' => 'Link P24 (po wygenerowaniu)',
            'p24_link_none' => '— brak linka, użyj akcji „Wygeneruj link P24"',
        ],
        'helper' => [
            'amount_cents' => 'Kwota w najmniejszej jednostce (grosze dla PLN, eurocenty dla EUR). '
                .'Auto-uzupełnione z cennika PlanAddon po wyborze powyżej.',
        ],
    ],

    'status' => [
        'pending' => 'Oczekuje płatności',
        'paid' => 'Opłacone',
        'failed' => 'Płatność nieudana',
        'cancelled' => 'Anulowane',
    ],

    'table' => [
        'column' => [
            'tenant' => 'Stajnia',
            'addon' => 'Add-on',
            'amount' => 'Kwota',
            'status' => 'Status',
            'paid_at' => 'Opłacone',
            'created_at' => 'Utworzone',
        ],
    ],

    'action' => [
        'generate_p24_link' => 'Wygeneruj link P24',
    ],

    'notify' => [
        'link_generated' => 'Link P24 wygenerowany — skopiuj poniżej i wyślij klientowi',
        'link_failed' => 'Nie udało się wygenerować linka P24',
    ],

    'return' => [
        'paid' => 'Zakup add-onu „{code}" został zaksięgowany — dziękujemy!',
        'pending' => 'Zakup add-onu „{code}" jest w trakcie weryfikacji.',
        'unknown' => 'Nie znaleziono zakupu add-onu.',
    ],
];
