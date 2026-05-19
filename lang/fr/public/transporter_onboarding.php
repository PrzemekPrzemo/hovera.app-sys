<?php

declare(strict_types=1);

return [
    'title' => 'Devenir transporteur',
    'heading' => 'Rejoignez hovera en tant que société de transport',
    'subtitle' => 'Marketplace de transport de chevaux — remplissez votre profil en 10 minutes, téléchargez '
        .'les documents et nous vérifierons le compte sous 2-3 jours ouvrables.',
    'no_commission_banner' => 'hovera.app ne perçoit aucune commission ni des transporteurs ni des clients — '
        .'uniquement un abonnement mensuel.',
    'promo' => [
        'heading' => '🎁 Promotion jusqu\'à fin juillet 2026',
        'body' => 'Toutes les fonctionnalités. Sans carte. Pas de conversion automatique en payant — '
            .'choisissez un plan uniquement quand vous êtes sûr de rester.',
        'body_yearly' => 'L\'inscription avant fin juillet 2026 offre 30 jours gratuits, '
            .'et le tarif annuel équivaut à 10 × le tarif mensuel (2 mois offerts). Offre limitée dans le temps.',
    ],
    'perks' => [
        'title' => 'Ce que vous obtenez',
        'item_1' => 'Profil public sur app.hovera.app/t/{slug} indexé par Google',
        'item_2' => 'Demandes directes + diffusion dans toute la Pologne (PWL)',
        'item_3' => 'Calculateur de devis + PDF automatique + paiement en ligne',
        'item_4' => 'Premier mois gratuit à partir de la vérification',
    ],
    'section' => [
        'company' => 'Données de l\'entreprise',
        'owner' => 'Contact — propriétaire / personne autorisée',
        'documents' => 'Documents requis',
        'terms' => 'CGV et consentements',
    ],
    'field' => [
        'name' => 'Nom complet de l\'entreprise',
        'name_hint' => 'Tel qu\'inscrit sur le registre fiscal.',
        'slug' => 'URL marketplace (slug)',
        'slug_hint' => 'Lettres minuscules, chiffres et tirets uniquement. Immuable après inscription.',
        'tax_id' => 'NIP (numéro fiscal)',
        'tax_id_hint' => 'Chiffres uniquement — 10 chiffres.',
        'regon' => 'REGON',
        'regon_hint' => '9 ou 14 chiffres.',
        'address' => 'Adresse de l\'entreprise',
        'owner_name' => 'Nom et prénom',
        'owner_email' => 'E-mail de contact',
        'owner_email_hint' => 'Nous enverrons un lien magique après vérification.',
        'owner_phone' => 'Téléphone de contact',
    ],
    'documents_disclaimer' => 'Nous exigeons 6 documents conformément à la loi PWL et aux règlements hovera. '
        .'Formats : PDF, JPG, PNG. Max 5 Mo par fichier.',
    'pwl_authorization' => [
        'label' => 'Autorisation PWL du transporteur',
        'description' => 'Autorisation de transport d\'animaux vivants selon le Règlement CE 1/2005. '
            .'Choisissez le type : T1 (trajets courts, jusqu\'à 8h) ou T2 (trajets longs, plus de 8h) — '
            .'et téléchargez le scan correspondant.',
        'type_t1' => 'Type 1 (transport jusqu\'à 8 heures)',
        'type_t2' => 'Type 2 (transport de plus de 8 heures)',
    ],
    'documents' => [
        'file_hint' => 'PDF, JPG ou PNG. Max 5 Mo.',
        'anonymized_heading' => 'Documents uniquement pour vérification',
        'anonymized_body' => 'Après vérification positive par l\'équipe Hovera, nous montrons les documents aux clients '
            .'UNIQUEMENT sous forme anonymisée (sans numéros de série, dates d\'expiration, données personnelles). '
            .'Publiquement visible UNIQUEMENT : « ✓ Vérifié par l\'équipe Hovera ».',
    ],
    'terms' => [
        'marketplace_position' => 'Hovera est une plateforme marketplace pour les transporteurs. '
            .'Nous ne sommes pas transporteur et ne sommes pas partie au contrat de transport. '
            .'Le contrat est conclu directement entre vous et le client, le paiement va sur votre compte.',
        'accept_html' => 'J\'accepte :regulamin, :marketplace et :privacy. Je déclare que '
            .'les documents téléchargés sont à jour.',
        'regulamin' => 'CGV hovera',
        'marketplace' => 'Règlement marketplace',
        'privacy' => 'Politique de confidentialité',
    ],
    'submit' => 'Soumettre l\'inscription',
    'errors' => [
        'heading' => 'Vérifiez le formulaire :',
        'slug_format' => 'Le slug ne peut contenir que des lettres minuscules, chiffres et tirets.',
        'slug_taken' => 'Ce slug est déjà pris.',
        'tax_id_format' => 'Le NIP doit comporter 10 chiffres.',
        'regon_format' => 'Le REGON doit comporter 9 ou 14 chiffres.',
        'terms' => 'Vous devez accepter les CGV.',
        'pwl_authorization_type_required' => 'Choisissez le type d\'autorisation PWL (T1 ou T2).',
        'provisioning_failed' => 'Impossible de créer le compte — réessayez dans un instant.',
    ],
    'notify' => ['thanks_silent' => 'Merci — nous examinerons votre demande.'],
    'thanks' => [
        'title' => 'Merci pour votre inscription',
        'heading' => 'Demande reçue !',
        'intro' => 'Le compte ":name" a été créé et attend la vérification des documents.',
        'step_1' => 'Nous vérifierons les documents sous 2-3 jours ouvrables.',
        'step_2' => 'Après vérification, nous enverrons un lien magique à votre e-mail.',
        'step_3' => 'Un essai gratuit de 1 mois démarre alors.',
        'contact_hint' => 'Une question ? Écrivez à :email.',
        'cta_directory' => 'Voir l\'annuaire des transporteurs',
    ],
];
