<?php

declare(strict_types=1);

return [
    'title' => 'Reitstunde buchen',
    'back' => '← Zurück zum Portal',
    'heading' => 'Reitstunde buchen',
    'subtitle' => 'Wählen Sie Pferd, Reitlehrer und Termin selbst · :tenant',
    'errors_heading' => 'Bitte prüfen Sie das Formular:',

    'no_horses' => 'Diesem Konto sind keine Pferde zugeordnet. Bitte kontaktieren Sie den Reitstall.',
    'no_dates' => 'Bei diesem Reitlehrer sind in nächster Zeit keine freien Termine verfügbar.',
    'no_slots' => 'Keine freien Zeiten an diesem Tag. Bitte wählen Sie einen anderen Tag.',

    'label' => [
        'horse' => 'Ihr Pferd',
        'horse_for' => 'Pferd, auf dem Sie reiten',
        'instructor' => 'Reitlehrer',
        'instructor_placeholder' => '— Reitlehrer wählen —',
        'day' => 'Tag',
        'slot' => 'Uhrzeit',
        'notes' => 'Anmerkungen (optional)',
        'notes_placeholder' => 'z. B. bevorzugter Reitplatz / Erfahrungsstand',
    ],

    'actions' => [
        'submit' => 'Buchungsanfrage senden',
    ],

    'errors' => [
        'disabled' => 'Online-Buchung ist für diesen Reitstall deaktiviert.',
        'horse_invalid' => 'Das gewählte Pferd gehört nicht zu Ihrem Konto.',
        'instructor_invalid' => 'Der Reitlehrer ist nicht verfügbar.',
        'slot_taken' => 'Dieser Termin wurde leider soeben vergeben. Bitte wählen Sie einen anderen.',
    ],

    'success_flash' => '✓ Ihre Buchungsanfrage wurde gesendet. Der Reitstall bestätigt sie, wir melden uns per E-Mail.',
    'disabled_flash' => 'Online-Buchung ist für diesen Reitstall deaktiviert — bitte kontaktieren Sie den Reitstall telefonisch.',
];
