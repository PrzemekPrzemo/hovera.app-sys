<?php

declare(strict_types=1);

return [
    'types' => [
        'individual' => 'Particulier',
        'family' => 'Famille',
        'organisation' => 'Entreprise / organisation',
    ],
    'types_short' => [
        'individual' => 'Particulier',
        'family' => 'Famille',
        'organisation' => 'Entreprise',
    ],

    'form' => [
        'section' => [
            'data' => 'Informations du client',
            'armir' => 'Identification du propriétaire (ARMiR)',
            'armir_description' => 'Requis pour les propriétaires de chevaux enregistrés dans la base centrale équine polonaise. EP (numéro de producteur attribué par l’ARMiR) — à défaut, saisissez le PESEL.',
            'address' => 'Adresse',
            'rodo' => 'RGPD',
            'notes' => 'Notes',
        ],
        'label' => [
            'type' => 'Type',
            'name' => 'Nom et prénom / Raison sociale',
            'phone' => 'Téléphone',
            'tax_id' => 'NIP / N° de TVA',
            'armir_producer_id' => 'N° EP (numéro de producteur ARMiR)',
            'armir_producer_id_placeholder' => 'par exemple 026123456789',
            'pesel' => 'PESEL',
            'street' => 'Rue et numéro',
            'postal_code' => 'Code postal',
            'city' => 'Ville',
            'country' => 'Pays',
            'rodo_consent_at' => 'Consentement RGPD donné le',
            'rodo_consent_source' => 'Origine du consentement',
            'notes' => 'Notes internes',
        ],
        'helper' => [
            'armir_producer_id' => 'Numéro de producteur attribué par l’ARMiR lors de l’enregistrement du cheval.',
            'pesel' => 'À saisir uniquement si le propriétaire n’a pas d’EP attribué par l’ARMiR.',
        ],
        'gus' => [
            'lookup_label' => 'Récupérer depuis le GUS',
            'invalid_nip' => 'NIP invalide (somme de contrôle).',
            'not_found' => 'Entreprise introuvable dans le GUS.',
            'success' => 'Données récupérées depuis le GUS.',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Nom',
            'type' => 'Type',
            'phone' => 'Téléphone',
            'horses_count' => 'Chevaux',
            'rodo' => 'RGPD',
            'created_at' => 'Ajouté le',
        ],
    ],

    'action' => [
        'issue_portal_link' => [
            'label' => 'Copier le lien du portail',
            'modal_heading' => 'Générer un lien de connexion pour :name ?',
            'modal_description' => 'Crée un magic link à usage unique (durée de vie 30 min). Vous pouvez le copier et l’envoyer manuellement au client, par exemple par SMS ou messagerie. Aucun e-mail requis.',
            'success_title' => 'Lien de connexion créé',
        ],
        'email_portal_link' => [
            'label' => 'Envoyer le lien par e-mail',
            'modal_heading' => 'Envoyer le lien de connexion à :name ?',
            'modal_description' => 'Nous enverrons un e-mail avec le lien de connexion à l’adresse :email. Le lien est valable 30 minutes, à usage unique.',
            'success_title' => 'Lien envoyé',
            'success_body' => 'E-mail avec le lien de connexion envoyé à :email.',
            'no_email' => 'Le client n’a pas d’adresse e-mail dans son profil.',
        ],
    ],
];
