<?php

declare(strict_types=1);

return [
    'title' => 'Demande de transport de chevaux',
    'heading' => 'Demande de transport de chevaux',
    'subtitle' => 'Remplissez le formulaire — nous enverrons votre demande à des transporteurs vérifiés. Les offres arrivent par e-mail.',
    'errors_heading' => 'Vérifiez :',

    'direct_target_banner' => 'Vous envoyez cette demande directement à :name. Seule cette entreprise répondra.',
    'direct_target_switch_to_broadcast' => 'Je préfère l\'envoyer à tous les transporteurs correspondants',

    'label' => [
        'customer_name' => 'Nom complet',
        'customer_email' => 'E-mail',
        'customer_phone' => 'Téléphone (optionnel)',
        'pickup_address' => 'De (prise en charge)',
        'dropoff_address' => 'Vers (livraison)',
        'preferred_date' => 'Date préférée',
        'preferred_time' => 'Heure (optionnel)',
        'flexible_date' => 'Date flexible (±2 jours)',
        'horse_count' => 'Nombre de chevaux',
        'notes' => 'Notes supplémentaires',
        'terms' => 'J’accepte le partage de mes données avec les transporteurs vérifiés. <a href="/polityka-prywatnosci" target="_blank">Politique de confidentialité</a>.',
    ],

    'placeholder' => [
        'pickup_address' => 'p. ex. Écurie, rue Principale 1, Paris',
        'dropoff_address' => 'p. ex. Lyon, avenue du Sport 1',
        'notes' => 'p. ex. chevaux de race, certificat requis, assurance OCS...',
    ],

    'action' => [
        'submit' => 'Envoyer',
    ],

    'error' => [
        'geocoding' => 'Adresse introuvable : :msg. Essayez ville + rue.',
        'terms' => 'Consentement requis pour partager les données.',
    ],

    'thanks_title' => 'Demande reçue',
    'thanks_heading' => 'Merci !',
    'thanks_body' => 'Demande envoyée aux transporteurs. Offres à :email sous 24 h.',
    'thanks_reference' => 'Numéro de référence',
];
