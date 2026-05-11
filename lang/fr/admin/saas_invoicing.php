<?php

declare(strict_types=1);

return [
    'navigation' => 'Numérotation des factures SaaS',
    'title' => 'Numérotation et modèles de factures hovera',

    'section' => [
        'numbering' => 'Numérotation',
        'numbering_help' => 'Modèle de numéro utilisé pour générer les factures SaaS successives (hovera → écurie). Jetons : {YYYY} année sur 4 chiffres, {YY} année sur 2 chiffres, {MM} mois sur 2 chiffres, {NNNN} séquence sur 4 chiffres avec zéros initiaux, {NN} séquence sur 2 chiffres, {SEQ} séquence sans padding.',
        'defaults' => 'Valeurs par défaut des factures',
        'text' => 'Contenu des champs fixes',
        'text_help' => 'Texte inséré sur chaque facture émise — conditions de paiement, pied de page avec le numéro de compte, coordonnées.',
    ],

    'field' => [
        'number_template' => 'Modèle de numérotation',
        'number_template_help' => 'Exemple : HVR/{YYYY}/{MM}/{NNNN} → HVR/2026/05/0042',
        'reset_cycle' => 'Cycle de réinitialisation de la séquence',
        'next_sequence' => 'Prochain numéro (surcharger)',
        'next_sequence_placeholder' => 'laisser vide pour continuer',
        'next_sequence_help' => 'Si vous saisissez par exemple 100, la prochaine facture émise utilisera la séquence 100 (puis 101, 102…). Utile après un import depuis un autre système.',
        'currency' => 'Devise',
        'vat_rate' => 'Taux de TVA',
        'due_days' => 'Délai de paiement',
        'due_days_suffix' => 'jours',
        'payment_terms' => 'Conditions de paiement',
        'payment_terms_placeholder' => 'par exemple « Payable sous 14 jours à compter de la date d’émission. Compte : … »',
        'footer_note' => 'Pied de page de la facture',
        'footer_note_help' => 'Imprimé en bas de chaque PDF de facture et inséré dans le XML KSeF en tant que champ optionnel.',
        'footer_note_placeholder' => 'par exemple « Merci pour votre confiance ! Des questions ? support@hovera.app »',
    ],

    'cycle' => [
        'monthly' => 'Mensuel (réinitialisation le 1er du mois)',
        'yearly' => 'Annuel (réinitialisation au 1er janvier)',
        'never' => 'Jamais (séquence continue)',
    ],

    'action' => [
        'save_button' => 'Enregistrer la configuration',
        'saved' => 'Configuration de la numérotation enregistrée.',
    ],
];
