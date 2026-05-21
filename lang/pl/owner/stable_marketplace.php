<?php

declare(strict_types=1);

return [
    'navigation' => 'Stajnie',
    'navigation_group' => 'Konie',
    'title' => 'Stajnie — znajdź pensjonat dla konia',
    'intro' => 'Lista aktywnych stajni w sieci Hovera. Wybierz tę, do której chcesz wysłać swojego konia — zarejestrowana stajnia dostanie powiadomienie i zaakceptuje lub odrzuci prośbę.',

    'empty' => [
        'heading' => 'Brak dostępnych stajni',
        'description' => 'Aktualnie żadna stajnia nie jest dostępna do pensjonatu w sieci Hovera. Zajrzyj później.',
    ],

    'action' => [
        'request_boarding' => 'Wyślij prośbę o boarding',
        'modal_heading' => 'Wyślij konia do stajni „:stable"',
        'horse_label' => 'Wybierz konia',
        'horse_helper' => 'Lista koni z Twojego centralnego rejestru. Jeśli koń jeszcze nie jest tu — dodaj go najpierw w panel "Moje konie".',
        'no_passport' => 'brak nr paszportu',
        'stable_missing' => 'Wybrana stajnia jest niedostępna. Odśwież stronę.',
        'horse_missing' => 'Wybrany koń nie należy do Ciebie lub został usunięty.',
        'success' => 'Prośba wysłana',
        'success_body' => 'Stajnia „:stable" widzi już Twoją prośbę o boarding dla „:horse" i powiadomienie zostało im wysłane. Czeka na ich akceptację.',
    ],
];
