<?php

declare(strict_types=1);

return [
    // Default Laravel keys
    'failed' => 'Ces identifiants ne correspondent pas à nos enregistrements.',
    'password' => 'Le mot de passe fourni est incorrect.',
    'throttle' => 'Trop de tentatives de connexion. Veuillez réessayer dans :seconds secondes.',

    // Hovera-specific
    'login' => [
        'title' => 'Connexion — hovera',
        'heading' => 'Connexion',
        'email' => 'E-mail',
        'password' => 'Mot de passe',
        'remember' => 'Se souvenir de moi',
        'submit' => 'Se connecter',
        'forgot_password' => 'Mot de passe oublié ?',
        'no_account' => 'Vous n’avez pas de compte ?',
        'register' => 'S’inscrire',
    ],

    'logout' => 'Se déconnecter',

    'two_factor' => [
        'setup_title' => 'Configuration 2FA — hovera',
        'setup_heading' => 'Activer l’authentification à deux facteurs (2FA)',
        'setup_intro' => 'Scannez le QR code avec une application d’authentification (Google Authenticator, Authy, 1Password) et saisissez le code à six chiffres généré pour confirmer.',
        'manual_entry' => 'Ou saisissez le secret manuellement :',
        'code_label' => 'Code 2FA',
        'confirm' => 'Confirmer et activer',
        'challenge_title' => 'Vérification 2FA — hovera',
        'challenge_heading' => 'Saisissez votre code 2FA',
        'challenge_intro' => 'Saisissez le code à six chiffres de votre application d’authentification, ou un code de récupération à usage unique.',
        'remember_device' => 'Se souvenir de cet appareil pendant 14 jours',
        'submit_challenge' => 'Se connecter',
        'invalid_code' => 'Code invalide.',
        'recovery_codes_title' => 'Codes de récupération — hovera',
        'recovery_codes_heading' => 'Vos codes de récupération',
        'recovery_codes_intro' => 'Conservez ces codes en lieu sûr. Chacun ne fonctionne qu’une seule fois — vous pouvez les utiliser si vous perdez l’accès à votre application d’authentification.',
        'recovery_codes_continue' => 'J’ai enregistré les codes, continuer',
    ],

    'password_reset' => [
        'request_title' => 'Réinitialisation du mot de passe',
        'email_sent' => 'Nous avons envoyé un lien de réinitialisation du mot de passe à votre adresse e-mail.',
        'reset_title' => 'Définir un nouveau mot de passe',
        'reset_button' => 'Réinitialiser le mot de passe',
    ],

    'tenant_select' => [
        'title' => 'Choisir une écurie — Hovera',
        'heading' => 'Choisir une écurie',
        'intro' => 'Votre compte a accès à :count écuries. Choisissez celle à laquelle vous souhaitez vous connecter.',
        'role_label' => ':slug · rôle : :role',
        'submit' => 'Accéder à l’écurie',
    ],

    'no_tenants' => [
        'title' => 'Aucune écurie disponible — Hovera',
        'heading' => 'Aucune écurie disponible',
        'intro' => 'Votre compte n’est pas encore associé à une écurie, ou votre accès a été révoqué. Contactez l’administrateur de l’écurie pour obtenir un accès.',
        'logout' => 'Se déconnecter',
    ],

    'invitation_accept' => [
        'title' => 'Activer le compte — Hovera',
        'heading' => 'Définir un mot de passe',
        'intro_with_tenant' => 'Vous rejoignez l’écurie <strong>:tenant</strong>.',
        'intro_account' => 'Compte : <strong>:email</strong>.',
        'intro_pwd' => 'Choisissez un mot de passe (min. 12 caractères) pour activer votre compte.',
        'password' => 'Nouveau mot de passe',
        'password_confirmation' => 'Confirmer le mot de passe',
        'submit' => 'Activer le compte et se connecter',
    ],
];
