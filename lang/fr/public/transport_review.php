<?php

declare(strict_types=1);

return [
    'anonymous_customer' => 'Client',
    'form' => [
        'title' => 'Avis pour :transporter — Hovera',
        'heading' => 'Laissez un avis',
        'lead' => 'Votre avis sur :transporter aide d\'autres propriétaires de chevaux. Le commentaire est facultatif.',
        'rating_label' => 'Votre note (1–5)',
        'comment_label' => 'Commentaire (facultatif)',
        'comment_placeholder' => 'Qu\'est-ce qui s\'est bien passé ? Que pourrait-on améliorer ? Votre avis apparaîtra sur le profil du transporteur.',
        'comment_hint' => 'Max 2000 caractères. Public, signé avec votre prénom et initiale du nom (ex. "Jan K.").',
        'submit' => 'Envoyer l\'avis',
        'disclaimer_intermediary' => 'Votre avis est publié <strong>tel quel</strong> sur le profil du transporteur. Hovera = marketplace de transport (<a href="/regulamin-marketplace" target="_blank">conditions</a>), pas partie au contrat. Le transporteur peut signaler un avis pour modération.',
    ],
    'thanks' => [
        'title' => 'Merci pour votre avis — Hovera',
        'heading' => 'Merci !',
        'body' => 'Votre avis a été publié sur le profil du transporteur. Nous apprécions le temps que vous y avez consacré.',
        'disclaimer_intermediary' => 'Hovera publie les avis marketplace tels quels.',
    ],
    'already' => [
        'title' => 'Avis déjà soumis — Hovera',
        'heading' => 'Vous avez déjà laissé un avis',
        'body' => 'Merci ! Votre avis est déjà publié. Chaque lien ne fonctionne qu\'une fois.',
        'see_profile' => 'Voir le profil du transporteur',
    ],
    'expired' => [
        'title' => 'Lien expiré — Hovera',
        'heading' => 'Lien d\'avis expiré',
        'body' => 'Le lien était valable 30 jours. Si vous souhaitez tout de même laisser un avis — écrivez à office@hovera.app.',
    ],
    'section' => [
        'title' => 'Avis clients',
        'count' => '{1} :count avis|[2,*] :count avis',
        'distribution_label' => 'Distribution des notes',
        'verified_badge' => 'Avis vérifié après transport effectué',
        'response_label' => 'Réponse de :transporter',
    ],
];
