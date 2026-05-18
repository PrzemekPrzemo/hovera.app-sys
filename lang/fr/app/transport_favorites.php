<?php

declare(strict_types=1);

return [
    'navigation' => 'Transporteurs favoris',
    'title' => 'Transporteurs favoris',

    'intro' => [
        'title' => 'Transporteurs favoris',
        'body' => 'Marquez jusqu’à :limit sociétés de transport comme favorites (actuellement :current). Lors d’une demande, la liste direct est pré-remplie — choisissez 1-3.',
    ],

    'search_placeholder' => 'Rechercher par nom, TVA, slug…',
    'empty' => 'Aucune société vérifiée.',

    'action' => [
        'add' => 'Ajouter',
        'remove' => 'Retirer',
    ],

    'notify' => [
        'added' => 'Ajouté aux favoris',
        'removed' => 'Retiré des favoris',
        'limit_reached' => 'Limite atteinte',
        'limit_body' => 'Maximum :limit favoris. Retirez-en un d’abord.',
        'error' => 'Erreur',
    ],
];
