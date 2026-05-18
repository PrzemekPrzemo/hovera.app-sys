<?php

declare(strict_types=1);

return [
    'anonymous_customer' => 'Kunde',
    'form' => [
        'title' => 'Bewertung für :transporter — Hovera',
        'heading' => 'Bewertung abgeben',
        'lead' => 'Ihre Bewertung von :transporter hilft anderen Pferdebesitzern. Der Kommentar ist optional.',
        'rating_label' => 'Ihre Bewertung (1–5)',
        'comment_label' => 'Kommentar (optional)',
        'comment_placeholder' => 'Was lief gut? Was kann verbessert werden? Ihre Bewertung erscheint im Profil des Transporteurs.',
        'comment_hint' => 'Max. 2000 Zeichen. Öffentlich signiert mit Vorname und erstem Buchstaben des Nachnamens (z.B. "Jan K.").',
        'submit' => 'Bewertung senden',
        'disclaimer_intermediary' => 'Ihre Bewertung wird <strong>unverändert</strong> im Profil des Transporteurs veröffentlicht. Hovera = Transport-Marketplace (<a href="/regulamin-marketplace" target="_blank">AGB</a>), keine Vertragspartei. Der Transporteur kann eine Bewertung zur Moderation melden.',
    ],
    'thanks' => [
        'title' => 'Danke für Ihre Bewertung — Hovera',
        'heading' => 'Danke!',
        'body' => 'Ihre Bewertung wurde im Profil des Transporteurs veröffentlicht. Wir wissen die Zeit zu schätzen — das hilft anderen Pferdebesitzern.',
        'disclaimer_intermediary' => 'Hovera veröffentlicht Marketplace-Bewertungen unverändert.',
    ],
    'already' => [
        'title' => 'Bewertung bereits abgegeben — Hovera',
        'heading' => 'Sie haben bereits eine Bewertung abgegeben',
        'body' => 'Danke! Ihre Bewertung ist bereits veröffentlicht. Jeder Link funktioniert nur einmal — Schutz vor Duplikaten.',
        'see_profile' => 'Transporteur-Profil ansehen',
    ],
    'expired' => [
        'title' => 'Link abgelaufen — Hovera',
        'heading' => 'Bewertungslink abgelaufen',
        'body' => 'Der Bewertungslink war 30 Tage gültig. Wenn Sie dennoch eine Bewertung abgeben möchten — schreiben Sie an office@hovera.app.',
    ],
    'section' => [
        'title' => 'Kundenbewertungen',
        'count' => '{1} :count Bewertung|[2,*] :count Bewertungen',
        'distribution_label' => 'Bewertungsverteilung',
        'verified_badge' => 'Verifizierte Bewertung nach abgeschlossenem Transport',
        'response_label' => 'Antwort von :transporter',
    ],
];
