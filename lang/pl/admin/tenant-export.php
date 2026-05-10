<?php

declare(strict_types=1);

return [
    'action' => [
        'label' => 'Eksportuj dane (post-trial)',
        'modal_heading' => 'Eksport danych — :name',
        'modal_description' => 'Generujemy ZIP zawierający klientów, konie, kalendarz (.ics), faktury oraz meta.json. Plik zostanie pobrany lokalnie i usunięty po wysłaniu — nie pozostaje na serwerze.',
    ],
    'toast' => [
        'success_title' => 'Eksport gotowy',
        'success_body' => 'Plik :file został przygotowany do pobrania.',
        'failure_title' => 'Eksport nie powiódł się',
    ],
];
