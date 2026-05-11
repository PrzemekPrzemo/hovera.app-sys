<?php

declare(strict_types=1);

return [
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
