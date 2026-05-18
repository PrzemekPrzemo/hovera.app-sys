<?php

declare(strict_types=1);

return [
    'title' => 'Devis de transport :number',
    'quote_number' => 'N° DE DEVIS',
    'accepted_banner' => 'Merci ! Devis accepté — le transporteur prendra contact.',
    'rejected_banner' => 'Devis refusé. Merci pour votre réponse.',
    'already_accepted' => 'Ce devis a déjà été accepté.',
    'already_rejected' => 'Ce devis a déjà été refusé.',

    'label' => [
        'from' => 'De',
        'to' => 'Vers',
        'date' => 'Date',
        'distance' => 'Distance',
        'valid_until' => 'Valide jusqu’au',
        'net' => 'HT',
        'vat' => 'TVA (:rate%)',
        'gross' => 'Total à payer',
    ],

    'action' => [
        'accept' => 'Accepter le devis',
        'reject' => 'Refuser',
    ],

    'payment' => [
        'heading' => 'Paiement',
        'disclaimer' => 'Le paiement est effectué DIRECTEMENT à :transporter. Hovera est un intermédiaire marketplace et N’accepte PAS les paiements. Adressez toute réclamation directement au transporteur.',
        'confirmed' => 'Paiement confirmé par le transporteur (:date)',
        'pay_now' => 'Payer maintenant (:amount :currency)',
        'instructions_heading' => 'Instructions de paiement :',
        'contact_transporter' => 'Contactez :transporter pour convenir du mode de paiement.',
    ],

    'footer' => 'Page sécurisée fournie par :app',

    'disclaimer_intermediary_html' => '<strong>En acceptant cette offre vous concluez un contrat DIRECTEMENT avec :transporter_name :transporter_nip.</strong> Hovera est intermédiaire de marketplace — NI partie au contrat, NI transporteur, NI responsable de l’exécution. Veuillez consulter les <a href="/regulamin-marketplace" target="_blank" style="color:inherit;text-decoration:underline;">conditions du marketplace transport</a>.',
];
