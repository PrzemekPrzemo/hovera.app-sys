<?php

declare(strict_types=1);

return [
    'title' => 'Créer une écurie',
    'thanks_title' => 'Consultez votre e-mail',

    'heading' => 'Créez votre écurie sur hovera',
    'subtitle' => 'Remplissez 4 champs, vous recevrez un e-mail avec un lien pour définir votre mot de passe. Sans carte de crédit.',

    'trial_strong' => '🎉 30 jours gratuits',
    'trial_text' => 'Toutes les fonctionnalités. Sans carte. Pas de bascule automatique vers une formule payante — vous choisirez un plan seulement quand vous serez sûr de rester.',

    'label' => [
        'name' => 'Nom de l’écurie',
        'slug' => 'Adresse URL de l’écurie',
        'owner_name' => 'Vos nom et prénom',
        'owner_email' => 'E-mail',
        'terms' => 'J’accepte les <a href="/regulamin" target="_blank">conditions générales</a> et la <a href="/polityka-prywatnosci" target="_blank">politique de confidentialité</a>',
    ],

    'placeholder' => [
        'name' => 'Écurie Pégase',
        'owner_name' => 'Anna Kowalska',
    ],

    'helper' => [
        'name' => 'C’est ainsi que votre écurie apparaîtra dans le panneau et sur la page publique.',
        'slug' => 'Uniquement des lettres minuscules, chiffres et tirets. Min. 3 caractères.',
        'owner_email' => 'C’est ici que nous enverrons le lien de définition du mot de passe.',
    ],

    'action' => [
        'submit' => 'Créer un compte + 30 jours gratuits',
    ],

    'footer' => [
        'demo' => 'Voir d’abord la démo',
        'pricing' => 'Voir les tarifs',
        'login' => 'J’ai déjà un compte',
    ],

    'errors' => [
        'heading' => 'Veuillez vérifier le formulaire :',
        'slug_format' => 'L’adresse ne peut contenir que des lettres minuscules, des chiffres et des tirets (pas de tiret en début ou en fin).',
        'slug_taken' => 'Cette adresse est déjà prise — essayez-en une autre, par exemple en ajoutant la ville ou un acronyme.',
        'terms' => 'Vous devez accepter les conditions générales.',
        'provisioning_failed' => 'Une erreur est survenue de notre côté. Réessayez dans un instant ou écrivez à support@hovera.app.',
    ],

    'thanks_heading' => '✓ Compte créé',
    'thanks_subtitle' => 'L’écurie :tenant a été créée. Nous vous avons envoyé un e-mail contenant un lien pour définir votre mot de passe.',
    'thanks_step_1' => 'Consultez votre boîte de réception (lien valable 7 jours).',
    'thanks_step_2' => 'Cliquez sur « Accepter l’invitation » dans l’e-mail.',
    'thanks_step_3' => 'Définissez un mot de passe — il s’agit de votre première connexion au panneau.',
    'thanks_step_4' => 'Vous arrivez dans le panneau /app — vous disposez de 30 jours d’essai sans limite.',
    'thanks_no_email' => 'Pas d’e-mail au bout de 5 minutes ?',
    'thanks_no_email_help' => 'Vérifiez vos spams. Si vous n’avez toujours rien — écrivez à support@hovera.app, nous vous aiderons.',
];
