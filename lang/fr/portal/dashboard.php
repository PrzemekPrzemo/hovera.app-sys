<?php

declare(strict_types=1);

return [
    'title' => 'Mes réservations — :tenant',
    'subtitle' => 'Portail client · :tenant',
    'logout' => 'Se déconnecter',

    'flash' => [
        'reschedule_success' => '✓ Réservation reprogrammée. Nous avons envoyé une confirmation par e-mail.',
    ],

    'sections' => [
        'upcoming' => 'Réservations à venir',
        'passes' => 'Vos abonnements',
        'history' => 'Historique',
        'unpaid_invoices' => 'Factures à régler',
        'messages' => 'Messages',
        'horses' => 'Vos chevaux',
    ],

    'empty' => [
        'upcoming' => 'Aucune réservation à venir.',
        'history' => 'Aucun historique de réservation.',
    ],

    'duration_min' => ':minutes min',
    'instructor_label' => 'Moniteur : :name',
    'horse_label' => 'Cheval : :name',

    'status' => [
        'requested' => 'En attente',
        'confirmed' => 'Confirmée',
        'completed' => 'Terminée',
        'cancelled' => 'Annulée',
        'no_show' => 'Absence',
    ],

    'actions' => [
        'reschedule' => 'Reprogrammer',
        'cancel' => 'Annuler',
        'view_all' => 'Tout voir →',
    ],

    'pass' => [
        'remaining' => ':remaining / :total restantes',
        'valid_until' => 'valide jusqu’au :date',
        'recent_uses' => 'Utilisations récentes',
        'lesson_label' => 'Leçon du :date',
    ],

    'invoice' => [
        'issued_at' => 'Émise : :date',
        'due_at' => 'Échéance : :date',
    ],

    'horse' => [
        'years_short' => 'ans',
        'overdue_pill' => ':count en retard',
        'upcoming_pill' => ':count sous 30 j',
        'ok_pill' => 'OK',
    ],

    'unread_messages' => '{0} 📬 :count nouveaux messages|{1} 📬 :count nouveau message|[2,*] 📬 :count nouveaux messages',
];
