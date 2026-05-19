<?php

declare(strict_types=1);

return [
    'navigation' => 'Formulaire à embarquer',
    'title' => 'Formulaire de demande à intégrer',

    'section' => [
        'origins' => 'Domaines autorisés',
        'origins_description' => 'Seuls les domaines listés peuvent soumettre le formulaire. URL complète avec schéma (`https://` ou `http://`), sans barre oblique finale.',
        'token' => 'Jeton API',
        'token_description' => 'Secret vérifié via l\'en-tête `X-Hovera-Embed-Token`. La régénération invalide immédiatement l\'ancien jeton — mettez à jour le snippet sur vos sites.',
        'snippet' => 'Snippet à coller',
        'snippet_description' => 'Copiez et collez dans le HTML de votre site. JS envoie la demande à Hovera ; les paiements de transport vont directement à vous (Hovera ne gère pas l\'argent).',
    ],

    'form' => [
        'origin_url' => 'URL du site (Origin)',
        'add_origin' => 'Ajouter un domaine',
        'token_status_label' => 'Statut du jeton',
        'token_missing' => 'Pas de jeton — générez-en un pour activer l\'embed.',
        'token_present' => 'Jeton défini (:preview).',
    ],

    'action' => [
        'save' => 'Enregistrer les domaines',
        'regenerate_token' => 'Générer un nouveau jeton',
        'regenerate_token_confirm' => 'L\'ancien jeton cessera de fonctionner immédiatement — tous les embeds existants devront être mis à jour. Continuer ?',
        'copy' => 'Copier le snippet',
        'copied' => 'Copié !',
    ],

    'notify' => [
        'saved' => 'Domaines enregistrés',
        'saved_body' => 'Domaines actifs : :count.',
        'token_regenerated' => 'Nouveau jeton généré',
        'token_regenerated_body' => 'L\'ancien jeton n\'est plus valide. Mettez à jour le snippet sur vos sites.',
    ],

    'snippet' => [
        'requires_token' => '<!-- Générez d\'abord un jeton API ci-dessus pour voir le code du snippet. -->',
    ],
];
