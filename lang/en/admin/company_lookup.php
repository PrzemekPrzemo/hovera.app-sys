<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'gus' => 'GUS BIR (REGON)',
            'gus_description' => 'GUS issues an API key after registration at https://api.stat.gov.pl. Free. The key rotates quarterly — remember to renew.',
            'krs' => 'KRS (public API)',
            'krs_description' => 'KRS Open Data API is public and requires no configuration. Hovera uses https://api-krs.ms.gov.pl. 30-day cache.',
        ],
        'label' => [
            'gus_api_key' => 'GUS API key',
            'gus_env' => 'Environment',
            'krs_status' => 'Status',
        ],
        'helper' => [
            'gus_api_key' => 'Test key from GUS docs: abcde12345abcde12345 (works only in the test environment).',
        ],
        'options' => [
            'env_test' => 'Test (wyszukiwarkaregontest.stat.gov.pl)',
            'env_prod' => 'Production (wyszukiwarkaregon.stat.gov.pl)',
            'krs_enabled' => '✓ Enabled (public API, no configuration needed)',
        ],
    ],

    'action' => [
        'saved' => 'GUS / KRS configuration saved',
    ],
];
