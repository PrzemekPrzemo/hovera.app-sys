<?php

declare(strict_types=1);

return [
    'title' => 'Réserver une leçon',
    'back' => '← Retour au tableau de bord',
    'heading' => 'Réserver une leçon',
    'subtitle' => 'Choisissez votre cheval, votre moniteur et votre créneau · :tenant',
    'errors_heading' => 'Veuillez vérifier le formulaire :',

    'no_horses' => 'Aucun cheval n’est rattaché à ce compte. Veuillez contacter l’écurie.',
    'no_dates' => 'Aucune date disponible avec ce moniteur prochainement.',
    'no_slots' => 'Aucun créneau libre ce jour-là. Choisissez un autre jour.',

    'label' => [
        'horse' => 'Votre cheval',
        'horse_for' => 'Cheval que vous monterez',
        'instructor' => 'Moniteur',
        'instructor_placeholder' => '— choisissez un moniteur —',
        'day' => 'Jour',
        'slot' => 'Heure',
        'notes' => 'Remarques (optionnel)',
        'notes_placeholder' => 'par exemple manège préféré / niveau',
    ],

    'actions' => [
        'submit' => 'Envoyer la demande de réservation',
    ],

    'errors' => [
        'disabled' => 'La réservation en ligne est désactivée pour cette écurie.',
        'horse_invalid' => 'Le cheval sélectionné n’est pas associé à votre compte.',
        'instructor_invalid' => 'Le moniteur n’est pas disponible.',
        'slot_taken' => 'Ce créneau vient d’être pris. Veuillez en choisir un autre.',
    ],

    'success_flash' => '✓ Demande de réservation envoyée. L’écurie confirmera et nous vous répondrons par e-mail.',
    'disabled_flash' => 'La réservation en ligne est désactivée pour cette écurie — veuillez les contacter par téléphone.',
];
