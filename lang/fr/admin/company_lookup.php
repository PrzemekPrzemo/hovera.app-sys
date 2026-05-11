<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'gus' => 'GUS BIR (REGON)',
            'gus_description' => 'GUS délivre une clé API après inscription sur https://api.stat.gov.pl. Gratuit. La clé est renouvelée chaque trimestre — pensez à la remplacer.',
            'krs' => 'KRS (API publique)',
            'krs_description' => 'L’API Open Data du KRS est publique et ne nécessite aucune configuration. Hovera utilise https://api-krs.ms.gov.pl. Cache de 30 jours.',
        ],
        'label' => [
            'gus_api_key' => 'Clé API GUS',
            'gus_env' => 'Environnement',
            'krs_status' => 'Statut',
        ],
        'helper' => [
            'gus_api_key' => 'Clé de test de la documentation GUS : abcde12345abcde12345 (fonctionne uniquement avec l’environnement de test).',
        ],
        'options' => [
            'env_test' => 'Test (wyszukiwarkaregontest.stat.gov.pl)',
            'env_prod' => 'Production (wyszukiwarkaregon.stat.gov.pl)',
            'krs_enabled' => '✓ Activé (API publique, sans configuration)',
        ],
    ],

    'action' => [
        'saved' => 'Configuration GUS / KRS enregistrée',
    ],
];
