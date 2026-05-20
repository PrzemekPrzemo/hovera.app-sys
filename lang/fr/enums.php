<?php

declare(strict_types=1);

return [
    'tenant_type' => [
        'stable' => 'Écurie',
        'transporter' => 'Société de transport',
    ],

    'vehicle_type' => [
        'truck' => 'Véhicule (motorisé)',
        'trailer' => 'Remorque',
    ],

    'quote_status' => [
        'draft' => 'Brouillon',
        'sent' => 'Envoyée',
        'accepted' => 'Acceptée',
        'rejected' => 'Rejetée',
        'expired' => 'Expirée',
        'withdrawn' => 'Retirée',
    ],

    'verification_status' => [
        'pending' => 'Documents attendus',
        'under_review' => 'En vérification',
        'verified' => 'Vérifié',
        'rejected' => 'Rejeté',
    ],

    'transport_lead_status' => [
        'open' => 'Ouvert',
        'quoted' => 'Devis envoyé',
        'accepted' => 'Accepté',
        'rejected' => 'Refusé',
        'expired' => 'Expiré',
        'cancelled' => 'Annulé',
    ],

    'transport_invoice_kind' => [
        'fv' => 'Facture (TVA)',
        'fv_proforma' => 'Facture pro forma',
        'fv_korekta' => 'Facture rectificative',
    ],

    'transport_invoice_status' => [
        'draft' => 'Brouillon',
        'issued' => 'Émise',
        'paid' => 'Payée',
        'overdue' => 'En retard',
        'void' => 'Annulée',
        'cancelled' => 'Retirée',
    ],

    'transporter_document_type' => [
        // @todo native review — traduction automatique depuis PL/EN.
        'company_registration' => 'Registre de la société',
        'company_registration_description' => 'Extrait du registre du commerce (KRS / CEIDG en PL, équivalent ailleurs — PDF/JPG).',

        // Legacy — obsolètes, masqués dans la nouvelle UI mais conservés pour la rétrocompatibilité.
        'animal_transport_cert' => 'Certificat de transport d’animaux (legacy)',
        'animal_transport_cert_description' => 'Ancien certificat UE 1/2005 — remplacé par le Certificat d’agrément du moyen de transport PLW.',
        'insurance_ocp' => 'Assurance RC transporteur (legacy)',
        'insurance_ocp_description' => 'Remplacée par la nouvelle entrée « Assurance RC transporteur » dans la liste PLW.',
        'insurance_ocs' => 'Assurance marchandises',
        'insurance_ocs_description' => 'Assurance pour les dommages à l’animal transporté. Optionnelle mais recommandée.',
        'vehicle_registration' => 'Carte grise du véhicule (legacy)',
        'vehicle_registration_description' => 'Remplacée par le Certificat d’agrément du moyen de transport PLW.',

        // PLW — régime polonais de transport intra-UE d’animaux vivants.
        // @todo native review — traduction automatique des textes réglementaires polonais.
        'road_carrier_license' => 'Licence d’exercice de la profession de Transporteur Routier',
        'road_carrier_license_description' => 'Délivrée par GITD ou le starosta selon Règl. UE 1071/2009 et la loi polonaise sur le transport routier de 2001.',
        'pwl_authorization_type1' => 'Autorisation transporteur PLW — Type 1 (< 8h)',
        'pwl_authorization_type1_description' => 'Autorisation PIW (inspection vétérinaire) pour transports de courte durée (≤ 8h). Choisir Type 1 pour les courts trajets uniquement.',
        'pwl_authorization_type2' => 'Autorisation transporteur PLW — Type 2 (> 8h)',
        'pwl_authorization_type2_description' => 'Autorisation PIW pour les trajets longs (> 8h). Couvre également les usages Type 1.',
        'pwl_driver_handler_certificate' => 'Certificat de compétence des conducteurs et convoyeurs (PLW)',
        'pwl_driver_handler_certificate_description' => 'Article 6 du Règl. UE 1/2005 — certificat de compétence pour conducteurs et convoyeurs. Téléverser pour toute l’équipe.',
        'pwl_vehicle_approval_certificate' => 'Certificat d’agrément du moyen de transport (PLW)',
        'pwl_vehicle_approval_certificate_description' => 'Règl. UE 1/2005 art. 18 (< 8h) ou art. 19 (> 8h). Obligatoire pour chaque véhicule utilisé.',
        'wash_disinfection_log' => 'Registre de lavage et désinfection du moyen de transport',
        'wash_disinfection_log_description' => 'Obligatoire selon la loi polonaise sur la protection de la santé animale de 2004. Téléverser les entrées des 12 derniers mois.',
        'carrier_liability_insurance' => 'Assurance RC transporteur',
        'carrier_liability_insurance_description' => 'Police de responsabilité civile du transporteur routier. Nous vérifions date d’expiration et montant de garantie.',

        'other' => 'Autre document',
        'other_description' => 'Licence communautaire, certificat d’inspection du travail, police cargo complémentaire, etc.',
    ],

    'boarding_frequency' => [
        'daily' => 'Quotidienne',
        'monthly' => 'Mensuelle',
        'per_use' => 'À l’utilisation',
        'once' => 'Ponctuelle',
    ],

    'calendar_entry_status' => [
        'requested' => 'Demandé',
        'confirmed' => 'Confirmé',
        'cancelled' => 'Annulé',
        'completed' => 'Terminé',
        'no_show' => 'Absence',
    ],

    'calendar_entry_type' => [
        'lesson_individual' => 'Cours individuel',
        'lesson_group' => 'Cours collectif',
        'training' => 'Entraînement',
        'care' => 'Soins (vétérinaire / maréchal-ferrant)',
        'event' => 'Événement / concours',
        'block' => 'Blocage',
    ],

    'health_record_type' => [
        'vaccination' => 'Vaccination',
        'deworming' => 'Vermifugation',
        'vet_visit' => 'Visite vétérinaire',
        'farrier' => 'Maréchal-ferrant',
        'dentist' => 'Dentiste',
        'check_up' => 'Contrôle',
        'medication' => 'Médicaments',
        'other' => 'Autre',
    ],

    'horse_document_kind' => [
        'passport' => 'Passeport équin',
        'contract' => 'Contrat de pension',
        'insurance' => 'Police / assurance',
        'vaccine_book' => 'Carnet de vaccination',
        'ownership_proof' => 'Preuve de propriété',
        'competition_licence' => 'Licence de compétition',
        'vet_certificate' => 'Certificat vétérinaire',
        'other' => 'Autre',
    ],

    'invoice_kind' => [
        'fv' => 'Facture avec TVA',
        'fv_proforma' => 'Facture pro forma',
        'fv_korekta' => 'Facture rectificative',
    ],

    'invoice_status' => [
        'draft' => 'Brouillon',
        'issued' => 'Émise',
        'paid' => 'Payée',
        'overdue' => 'En retard',
        'void' => 'Annulée',
        'cancelled' => 'Rectifiée',
    ],

    'pass_status' => [
        'active' => 'Actif',
        'exhausted' => 'Épuisé',
        'expired' => 'Expiré',
        'cancelled' => 'Annulé',
    ],

    'payment_provider' => [
        'none' => 'Aucun (paiement hors ligne)',
        'stub' => 'Test (développeur)',
        'p24' => 'Przelewy24',
        'payu' => 'PayU',
        'stripe' => 'Stripe',
        'mollie' => 'Mollie',
    ],

    'payment_status' => [
        'pending' => 'En attente',
        'processing' => 'En cours de traitement',
        'succeeded' => 'Payée',
        'failed' => 'Échouée',
        'refunded' => 'Remboursée',
    ],

    'recurrence_pattern' => [
        'daily' => 'Quotidienne',
        'weekly' => 'Hebdomadaire',
        'monthly' => 'Mensuelle',
    ],

    'stable_activity_type' => [
        'feeding' => 'Alimentation',
        'grooming' => 'Pansage / soins',
        'turnout' => 'Sortie au paddock',
        'exercise' => 'Travail du cheval',
        'box_cleaning' => 'Nettoyage du box',
        'transport_event' => 'Déplacement / événement',
        'other' => 'Autre',
    ],

    'feeding_meal' => [
        'breakfast' => 'Matin',
        'midday' => 'Midi',
        'evening' => 'Soir',
        'night' => 'Nuit',
    ],
];
