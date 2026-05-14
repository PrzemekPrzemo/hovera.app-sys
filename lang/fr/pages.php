<?php

declare(strict_types=1);

return [
    'profile' => [
        'navigation' => 'Profil',
        'title' => 'Votre profil',
    ],

    'calendar' => [
        'navigation' => 'Planning du jour',
    ],

    'tenant_settings' => [
        'navigation' => 'Paramètres de l’écurie',
        'title' => 'Paramètres de l’écurie',
    ],

    'invoicing_settings' => [
        'navigation' => 'Factures et facturation',
        'title' => 'Factures et facturation',
    ],

    'payment_settings' => [
        'navigation' => 'Paiements en ligne',
        'title' => 'Paiements en ligne',
    ],

    'ksef_settings' => [
        'navigation' => 'KSeF (e-factures)',
        'title' => 'KSeF — système national polonais de facturation électronique',
    ],

    'company_lookup' => [
        'navigation' => 'GUS / KRS',
        'title' => 'Vérification d’entreprise — GUS / KRS',
    ],

    'my_tasks' => [
        'navigation' => 'Mes tâches',
        'title' => 'Mes tâches',
        'signed_in_as' => 'Connecté en tant que spécialiste',
        'sections' => [
            'overdue' => 'En retard',
            'upcoming' => 'Prochains soins (30 jours)',
            'recent' => 'Récemment effectués (30 jours)',
        ],
        'empty' => [
            'overdue' => 'Aucune tâche en retard — bravo !',
            'upcoming' => 'Aucun soin prévu dans les 30 prochains jours.',
            'recent' => 'Aucune entrée pour les 30 derniers jours.',
        ],
        'overdue_by_days' => '{1} en retard d’1 jour|[2,*] en retard de :days jours',
        'in_days' => '{0} aujourd’hui|{1} demain|[2,*] dans :days jours',
    ],

    'help' => [
        'navigation' => 'Aide',
        'title' => 'Centre d’aide',
        'tab' => [
            'manual' => 'Manuel d’utilisation',
            'legal' => 'Documents juridiques',
        ],
        'persona' => [
            'owner' => 'Propriétaire / admin',
            'owner_desc' => 'Panel complet, finances, équipe, paramètres de l’écurie.',
            'employee' => 'Employé / moniteur',
            'employee_desc' => 'Agenda, clients, chevaux — opérations quotidiennes.',
            'specialist' => 'Vétérinaire / spécialiste',
            'specialist_desc' => 'Dossiers de santé, visites, traitement des chevaux.',
            'client' => 'Client de l’écurie',
            'client_desc' => 'Portail : réservations, cartes, mon cheval.',
        ],
        'legal' => [
            'open_in_new_tab' => 'Ouvrir la version publique',
        ],
        'topbar' => [
            'help' => 'Centre d’aide',
            'report_bug' => 'Signaler un bug / une suggestion',
        ],
        'bug_report' => [
            'title' => 'Signaler un bug ou une suggestion',
            'lead' => 'Votre signalement va directement à l’équipe hovera sur Todoist — avec l’URL de la page où vous êtes.',
            'kind_label' => 'Type',
            'kind_bug' => 'Bug',
            'kind_idea' => 'Suggestion / changement',
            'subject_label' => 'Titre court',
            'subject_placeholder' => 'ex. Impossible de supprimer une carte',
            'description_label' => 'Description',
            'description_placeholder' => 'Que s’est-il passé ? Que devait-il se passer ? Étapes pour reproduire.',
            'screenshot_label' => 'Capture d’écran (PNG/JPG, optionnel)',
            'submit' => 'Envoyer le signalement',
            'cancel' => 'Annuler',
            'success' => 'Merci — votre signalement a été envoyé.',
            'error' => 'L’envoi a échoué. Réessayez ou écrivez à support@hovera.app.',
        ],
    ],

    'reports' => [
        'month_picker' => 'Mois',
        'apply' => 'Afficher',
        'empty' => 'Aucune donnée pour le mois sélectionné.',
        'col_item' => 'Élément',
        'col_total' => 'Total HT',

        'revenue' => [
            'navigation' => 'Chiffre d’affaires',
            'title' => 'Rapport mensuel — chiffre d’affaires',
            'total_heading' => 'Total HT · :month',
            'invoice_count' => 'Factures sur la période : :count',
            'top_items' => 'Top 10 des prestations',
            'bucket' => [
                'boarding' => 'Pension',
                'lessons' => 'Leçons',
                'passes' => 'Abonnements',
                'other' => 'Autre',
            ],
        ],

        'aging' => [
            'navigation' => 'Balance âgée',
            'title' => 'Balance âgée des créances',
            'total_heading' => 'Total impayé',
            'list_heading' => 'Liste des factures en retard',
            'empty' => 'Aucune facture en retard — tout est payé.',
            'col_invoice' => 'N° facture',
            'col_client' => 'Client',
            'col_due_at' => 'Échéance',
            'col_days_overdue' => 'Jours de retard',
            'col_amount' => 'Montant TTC',
            'days' => 'jours',
            'bucket' => [
                '0_30' => '1–30 jours',
                '31_60' => '31–60 jours',
                '61_90' => '61–90 jours',
                '90_plus' => '> 90 jours',
            ],
        ],

        'horse_utilization' => [
            'navigation' => 'Utilisation des chevaux',
            'title' => 'Utilisation des chevaux',
            'heading' => 'Leçons par cheval · :month',
            'subtitle' => 'Nombre de réservations confirmées / terminées sur le mois sélectionné. Au-delà de 25 leçons = risque de surmenage.',
            'col_horse' => 'Cheval',
            'col_lessons' => 'Leçons',
            'col_hours' => 'Heures',
        ],

        'instructor_utilization' => [
            'navigation' => 'Utilisation des moniteurs',
            'title' => 'Utilisation des moniteurs',
            'heading' => 'Heures et présence · :month',
            'col_instructor' => 'Moniteur',
            'col_lessons' => 'Leçons',
            'col_hours' => 'Heures',
            'col_cancelled' => 'Annulées',
            'col_no_show' => 'Absences',
            'col_attendance' => 'Présence',
        ],
    ],

    'bulk_invoicing' => [
        'navigation' => 'Factures mensuelles en masse',
        'title' => 'Facturation en masse — pension mensuelle',
        'month_picker' => 'Mois à facturer',
        'refresh' => 'Actualiser l’aperçu',
        'helper' => 'Génère un brouillon de facture pour chaque client à partir des prestations de pension actives sur ses chevaux. Les abonnements sont facturés à la vente et exclus du traitement en masse. Chaque brouillon est ensuite émis individuellement dans Factures → Émettre.',
        'preview_heading' => 'Aperçu · :month · :count clients',
        'empty' => 'Aucun montant à facturer pour le mois sélectionné. Vérifiez que vos chevaux disposent de prestations de pension actives sur la période.',
        'items_suffix' => 'lignes',
        'col_item' => 'Prestation',
        'col_qty' => 'Quantité',
        'col_unit_price' => 'Prix unitaire',
        'col_net' => 'HT',
        'col_gross' => 'TTC',
        'totals' => 'Total (sélection ou tous) :',
        'net_short' => 'HT',
        'gross_short' => 'TTC',
        'actions' => [
            'generate' => 'Générer les brouillons',
        ],
        'confirm' => [
            'heading' => 'Générer les brouillons de factures ?',
            'description' => 'Nous créerons des brouillons de factures pour :month pour les clients sélectionnés (ou pour tous ceux de l’aperçu). Chacun sera émis séparément depuis Factures.',
            'submit' => 'Oui, générer',
        ],
        'flash' => [
            'success' => ':count brouillons générés. Ouvrez Factures pour les émettre.',
        ],
    ],
];
