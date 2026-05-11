<?php

declare(strict_types=1);

return [
    // Éléments communs utilisés dans plusieurs notifications.
    'common' => [
        'greeting' => 'Bonjour !',
        'greeting_named' => 'Bonjour :name !',
        'salutation_prefix' => '— ',
        'field' => [
            'term' => 'Date et heure',
            'instructor' => 'Moniteur',
            'horse' => 'Cheval',
            'arena' => 'Manège',
            'address' => 'Adresse',
            'phone' => 'Téléphone de l’écurie',
            'old_date' => 'Ancienne date',
            'new_date' => 'Nouvelle date',
            'cancelled_term' => 'Créneau annulé',
            'from' => 'De',
            'subject' => 'Objet',
            'issued_at' => 'Date d’émission',
            'gross_amount' => 'Montant TTC',
            'due_date' => 'Échéance',
            'client' => 'Client',
            'client_note' => 'Note du client',
        ],
        'duration_minutes' => ':minutes min',
        'cancel_action' => 'Annuler la réservation',
        'cancel_policy' => 'Si vous devez annuler, cliquez ci-dessous. Une annulation au moins :hours heures avant la leçon est sans frais.',
        'portal_link' => 'Retrouvez toutes vos réservations dans le portail client : [:url](:url)',
    ],

    'booking_confirmed' => [
        'subject' => 'Réservation confirmée — :tenant',
        'line_intro' => 'Votre réservation chez **:tenant** a été confirmée.',
        'line_signoff' => 'À très bientôt !',
    ],

    'booking_cancelled' => [
        'subject' => 'Réservation annulée — :tenant',
        'line_by_client' => 'Votre réservation chez **:tenant** a été annulée à votre demande.',
        'line_by_stable' => 'L’écurie **:tenant** a annulé votre réservation. Veuillez contacter l’écurie pour plus de détails.',
        'pass_restored' => 'Votre abonnement a été recrédité — vous pouvez l’utiliser pour votre prochaine réservation.',
        'pass_not_restored' => 'Votre abonnement (s’il a été utilisé) n’a pas été recrédité — l’annulation est intervenue après le délai prévu.',
    ],

    'booking_reminder' => [
        'subject' => 'Rappel : demain à :time — :tenant',
        'line_intro' => 'Rappel concernant votre réservation de demain.',
        'cancel_policy' => 'Si vous devez annuler, faites-le dès que possible — une annulation au moins :hours heures avant la leçon est sans frais.',
        'line_signoff' => 'À demain !',
    ],

    'booking_requested' => [
        'subject' => 'Nous avons reçu votre demande — :tenant',
        'line_intro' => 'Merci pour votre demande de réservation chez **:tenant**.',
        'line_processing' => 'L’écurie confirmera votre réservation par e-mail (généralement dans les heures qui suivent) et vous attribuera un cheval.',
        'line_pass_warning' => 'Si vous n’annulez pas à temps, votre abonnement (s’il est utilisé) sera décompté.',
    ],

    'booking_rescheduled' => [
        'subject' => 'Réservation reprogrammée — :tenant',
        'line_intro' => 'Votre réservation chez **:tenant** a été reprogrammée.',
        'line_undo' => 'Si cette reprogrammation est une erreur, vous pouvez annuler et réserver un nouveau créneau.',
        'portal_link' => 'Gérez vos réservations dans le portail client : [:url](:url)',
    ],

    'client_portal_magic_link' => [
        'subject' => 'Connexion au portail — :tenant',
        'line_intro' => 'Cliquez ci-dessous pour vous connecter au portail client de **:tenant**.',
        'action' => 'Se connecter',
        'line_ttl' => 'Ce lien est valable :minutes minutes et ne peut être utilisé qu’une seule fois.',
        'line_security' => 'Si ce n’est pas vous qui essayez de vous connecter, ignorez ce message.',
    ],

    'horse_message' => [
        'subject_default' => 'Nouveau message — :horse — :tenant',
        'subject_with_subject' => ':subject (:horse)',
        'line_intro' => 'Vous avez reçu un nouveau message concernant le cheval **:horse** (:tenant).',
        'attachments_one' => '📎 1 pièce jointe',
        'attachments_many' => '📎 :count pièces jointes',
        'action' => 'Ouvrir le message',
    ],

    'invoice_issued' => [
        'subject' => ':kind :number — :tenant',
        'line_intro' => 'Nous avons émis :kind **:number** depuis l’écurie **:tenant**.',
        'action_pay' => 'Voir la facture et payer',
        'action_view' => 'Voir la facture',
        'line_offline_payment' => 'Merci de régler par virement bancaire sur le compte de l’écurie — les coordonnées sont disponibles dans le portail client.',
        'line_thanks' => 'Merci !',
    ],

    'new_booking_request' => [
        'subject' => 'Nouvelle demande en ligne — :tenant',
        'line_intro' => 'Un client a demandé une leçon chez **:tenant** :',
        'client_format' => ':name (:email)',
        'client_format_with_phone' => ':name (:email, tél. :phone)',
        'line_action_required' => 'Pour accepter, ouvrez la réservation, attribuez un cheval et passez le statut à « Confirmée ».',
        'action' => 'Ouvrir la réservation',
        'line_horse_assignment' => 'Un cheval ne peut être attribué qu’au moment de la confirmation — le système l’exige avant le changement de statut.',
        'salutation' => '— Hovera',
    ],

    'user_invitation' => [
        'subject_with_tenant' => 'Invitation à rejoindre :tenant — Hovera',
        'subject_default' => 'Invitation à rejoindre Hovera',
        'line_with_tenant' => 'Vous avez été ajouté à l’écurie **:tenant** dans Hovera:role.',
        'line_with_tenant_role' => ' avec le rôle *:role*',
        'line_default' => 'Vous avez reçu une invitation à rejoindre Hovera.',
        'line_setup' => 'Pour activer votre compte et définir un mot de passe, cliquez ci-dessous.',
        'action' => 'Définir le mot de passe et se connecter',
        'line_expires' => 'Ce lien expire le :date (UTC).',
        'line_security' => 'Si ce n’est pas vous, vous pouvez ignorer ce message — sans clic, le compte ne sera pas activé.',
        'salutation' => '— Hovera',
    ],
];
