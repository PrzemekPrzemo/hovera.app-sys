<?php

declare(strict_types=1);

return [
    'navigation' => 'Coordonnées de l’entreprise hovera',
    'title' => 'Coordonnées de l’entreprise hovera (émetteur des factures)',

    'section' => [
        'identity' => 'Identification',
        'identity_help' => 'Utilisées comme coordonnées du vendeur sur les factures SaaS émises aux écuries (KSeF, PDF, XML FA(3)).',
        'address' => 'Adresse du siège',
        'contact' => 'Contact',
        'bank' => 'Compte bancaire',
        'bank_help' => 'L’IBAN figure sur les factures comme compte destinataire (lorsqu’un client paie par virement classique au lieu de Stripe/P24).',
    ],

    'field' => [
        'name' => 'Raison sociale',
        'legal_form' => 'Forme juridique',
        'nip' => 'NIP',
        'regon' => 'REGON',
        'krs' => 'KRS',
        'court' => 'Tribunal d’enregistrement',
        'capital' => 'Capital social',
        'street' => 'Rue et numéro',
        'postal_code' => 'Code postal',
        'city' => 'Ville',
        'country' => 'Pays (code ISO)',
        'email' => 'E-mail',
        'phone' => 'Téléphone',
        'bank_name' => 'Nom de la banque',
        'iban' => 'IBAN',
        'swift' => 'SWIFT/BIC',
    ],

    'action' => [
        'save_button' => 'Enregistrer les coordonnées',
        'saved' => 'Coordonnées de l’entreprise enregistrées.',
    ],
];
