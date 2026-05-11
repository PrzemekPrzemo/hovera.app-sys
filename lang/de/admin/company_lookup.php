<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'gus' => 'GUS BIR (REGON)',
            'gus_description' => 'Der API-Schlüssel wird nach der Registrierung unter https://api.stat.gov.pl vom GUS ausgegeben. Kostenlos. Der Schlüssel rotiert quartalsweise — denken Sie an den Austausch.',
            'krs' => 'KRS (öffentliche API)',
            'krs_description' => 'Die KRS-Open-Data-API ist öffentlich und erfordert keine Konfiguration. Hovera nutzt https://api-krs.ms.gov.pl. Cache 30 Tage.',
        ],
        'label' => [
            'gus_api_key' => 'GUS-API-Schlüssel',
            'gus_env' => 'Umgebung',
            'krs_status' => 'Status',
        ],
        'helper' => [
            'gus_api_key' => 'Test-Schlüssel aus der GUS-Dokumentation: abcde12345abcde12345 (funktioniert nur mit der Test-Umgebung).',
        ],
        'options' => [
            'env_test' => 'Test (wyszukiwarkaregontest.stat.gov.pl)',
            'env_prod' => 'Produktion (wyszukiwarkaregon.stat.gov.pl)',
            'krs_enabled' => '✓ Aktiviert (öffentliche API, keine Konfiguration)',
        ],
    ],

    'action' => [
        'saved' => 'GUS- / KRS-Konfiguration gespeichert',
    ],
];
