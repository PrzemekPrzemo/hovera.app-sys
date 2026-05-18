<?php

declare(strict_types=1);

return [
    'kpi' => [
        'mrr_month' => 'Revenu ce mois',
        'mrr_month_desc' => 'Factures payées depuis le début du mois.',
        'receivables' => 'Créances',
        'receivables_desc' => 'Factures émises en attente de paiement.',
        'overdue' => 'Factures en retard',
        'overdue_desc' => 'Montant total :sum.',
        'pending_quotes' => 'Devis en attente',
        'pending_quotes_desc' => 'Envoyés, encore dans la période de validité.',
    ],

    'pending_invoices' => [
        'heading' => 'Devis sans facture',
        'description' => 'Devis acceptés non encore facturés.',
        'customer' => 'Client',
        'accepted_at' => 'Accepté le',
        'gross_total' => 'TTC',
        'issue' => 'Émettre la facture',
    ],

    'top_corridors' => [
        'heading' => 'Corridors principaux',
        'description' => '10 paires « de→vers » les plus fréquentes.',
        'empty' => 'Pas encore de données — aucun devis émis.',
    ],

    'upcoming' => [
        'heading' => 'Prochains transports',
        'description' => 'Devis acceptés dont la date est aujourd’hui ou demain.',
        'today' => 'Aujourd’hui',
        'tomorrow' => 'Demain',
        'empty' => 'Aucun transport.',
    ],

    'leads_kpi' => [
        'leads_week' => 'Leads (7 jours)',
        'leads_week_desc' => 'Demandes reçues la semaine passée.',
        'win_rate' => 'Taux de conversion (30 j)',
        'win_rate_desc' => 'Acceptés / total réponses sur 30 jours.',
        'win_rate_no_data' => 'Aucune donnée sur les 30 derniers jours.',
        'vs_prev' => ':delta vs période précédente',
    ],

    'upcoming_week' => [
        'heading' => 'Transports de la semaine à venir',
        'description' => 'Devis acceptés avec date de service dans les 7 prochains jours.',
        'date' => 'Date',
        'customer' => 'Client',
        'route' => 'Trajet',
        'driver' => 'Chauffeur',
        'gross' => 'TTC',
        'view' => 'Ouvrir',
        'empty_heading' => 'Aucun transport prévu',
        'empty_description' => 'Rien pour les 7 prochains jours — chiffrez une nouvelle mission.',
        'empty_action' => 'Ouvrir le calculateur',
    ],

    'top_paid' => [
        'heading' => 'Top 5 factures payées (90 j)',
        'description' => 'Les plus gros payeurs du dernier trimestre.',
        'number' => 'Numéro',
        'customer' => 'Client',
        'paid_at' => 'Payée le',
        'total' => 'TTC',
        'view' => 'Ouvrir',
        'empty_heading' => 'Aucune facture payée',
        'empty_description' => 'Aucune facture marquée payée dans les 90 derniers jours.',
    ],

    'routes_heatmap' => [
        'heading' => 'Routes principales (voïvodies, 90 j)',
        'description' => 'Paires « de → vers » issues des demandes reçues — où vous opérez réellement.',
        'empty' => 'Pas de données — aucune réponse à un lead dans les 90 derniers jours.',
    ],
];
