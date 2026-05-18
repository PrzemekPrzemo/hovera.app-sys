<?php

declare(strict_types=1);

return [
    'anonymous_customer' => 'Klient',

    'form' => [
        'title' => 'Opinia o przewoźniku :transporter — Hovera',
        'heading' => 'Wystaw opinię o transporcie',
        'lead' => 'Twoja opinia o przewoźniku :transporter pomoże innym właścicielom koni wybrać sprawdzoną firmę. Pole „komentarz" jest opcjonalne.',
        'rating_label' => 'Twoja ocena (1–5)',
        'comment_label' => 'Komentarz (opcjonalnie)',
        'comment_placeholder' => 'Co poszło dobrze? Co warto poprawić? Twoja opinia trafi pod profil przewoźnika.',
        'comment_hint' => 'Max 2000 znaków. Komentarz jest publiczny, podpisany Twoim imieniem i pierwszą literą nazwiska (np. „Jan K.").',
        'submit' => 'Wyślij opinię',
        'disclaimer_intermediary' => 'Opinia trafia publicznie pod profil przewoźnika i jest <strong>niezmieniana</strong> przez Hoverę. Hovera = marketplace transportu (<a href="/regulamin-marketplace" target="_blank">regulamin</a>), nie strona umowy przewozu. Przewoźnik może zgłosić opinię do moderacji, gdy uzna ją za niezgodną z faktami.',
    ],

    'thanks' => [
        'title' => 'Dziękujemy za opinię — Hovera',
        'heading' => 'Dziękujemy!',
        'body' => 'Twoja opinia została opublikowana pod profilem przewoźnika. Doceniamy każdą sekundę, którą poświęciłeś — to realna pomoc dla innych właścicieli koni.',
        'disclaimer_intermediary' => 'Hovera publikuje opinie marketplace\'u w niezmienionej formie. Możesz zobaczyć swoją opinię na <a href="/regulamin-marketplace" target="_blank">stronie przewoźnika</a> (link z maila).',
    ],

    'already' => [
        'title' => 'Opinia już wystawiona — Hovera',
        'heading' => 'Już zostawiłeś opinię',
        'body' => 'Dziękujemy! Twoja opinia jest już opublikowana. Każdy link działa tylko raz — to zabezpieczenie przed duplikatami.',
        'see_profile' => 'Zobacz profil przewoźnika',
    ],

    'expired' => [
        'title' => 'Link wygasł — Hovera',
        'heading' => 'Link do opinii wygasł',
        'body' => 'Link do wystawienia opinii był aktywny przez 30 dni. Jeśli chcesz nadal podzielić się opinią — napisz na office@hovera.app, pomożemy.',
    ],

    'section' => [
        'title' => 'Opinie klientów',
        'count' => '{1} :count opinia|{2,3,4} :count opinie|[5,*] :count opinii',
        'distribution_label' => 'Rozkład ocen',
        'verified_badge' => 'Zweryfikowana opinia po zaakceptowanym transporcie',
        'response_label' => 'Odpowiedź firmy :transporter',
    ],
];
