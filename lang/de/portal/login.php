<?php

declare(strict_types=1);

return [
    'login' => [
        'title' => 'Kundenportal — :tenant',
        'heading' => 'Kundenportal — :tenant',
        'intro' => 'Geben Sie die E-Mail-Adresse ein, an die Buchungsbestätigungen gesendet wurden. Sie erhalten einen Anmeldelink.',
        'email' => 'E-Mail',
        'submit' => 'Anmeldelink senden',
        'back' => '← Zurück zur Reitstall-Seite',
    ],

    'sent' => [
        'title' => 'Posteingang prüfen — :tenant',
        'heading' => 'Posteingang prüfen',
        'body' => 'Falls die Adresse <strong>:email</strong> mit einem Konto in <strong>:tenant</strong> verknüpft ist, haben wir einen Anmeldelink gesendet.',
        'ttl' => 'Der Link ist 30 Minuten gültig.',
        'back' => '← Zurück',
    ],

    'invalid' => [
        'title' => 'Link ungültig — :tenant',
        'heading' => 'Link ungültig',
        'body' => 'Dieser Anmeldelink ist abgelaufen oder wurde bereits verwendet. Links sind einmalig und 30 Minuten gültig.',
        'request_new' => 'Neuen Link anfordern',
    ],
];
