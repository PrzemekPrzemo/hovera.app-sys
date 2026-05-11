<?php

declare(strict_types=1);

return [
    // Gemeinsame Elemente, die in mehreren Benachrichtigungen verwendet werden.
    'common' => [
        'greeting' => 'Guten Tag!',
        'greeting_named' => 'Guten Tag, :name!',
        'salutation_prefix' => '— ',
        'field' => [
            'term' => 'Termin',
            'instructor' => 'Reitlehrer',
            'horse' => 'Pferd',
            'arena' => 'Reitplatz',
            'address' => 'Adresse',
            'phone' => 'Telefon des Reitstalls',
            'old_date' => 'Altes Datum',
            'new_date' => 'Neues Datum',
            'cancelled_term' => 'Stornierter Termin',
            'from' => 'Von',
            'subject' => 'Betreff',
            'issued_at' => 'Ausstellungsdatum',
            'gross_amount' => 'Bruttobetrag',
            'due_date' => 'Fälligkeitsdatum',
            'client' => 'Kunde',
            'client_note' => 'Kundennotiz',
        ],
        'duration_minutes' => ':minutes Min.',
        'cancel_action' => 'Buchung stornieren',
        'cancel_policy' => 'Falls Sie stornieren müssen, klicken Sie unten. Eine Stornierung mindestens :hours Stunden vor der Reitstunde ist kostenfrei.',
        'portal_link' => 'Alle Buchungen finden Sie im Kundenportal: [:url](:url)',
    ],

    'booking_confirmed' => [
        'subject' => 'Buchung bestätigt — :tenant',
        'line_intro' => 'Ihre Buchung im Reitstall **:tenant** wurde bestätigt.',
        'line_signoff' => 'Bis bald!',
    ],

    'booking_cancelled' => [
        'subject' => 'Buchung storniert — :tenant',
        'line_by_client' => 'Ihre Buchung im Reitstall **:tenant** wurde gemäß Ihrer Anfrage storniert.',
        'line_by_stable' => 'Der Reitstall **:tenant** hat Ihre Buchung storniert. Kontaktieren Sie den Reitstall für Details.',
        'pass_restored' => 'Die Reitkarte wurde zurückerstattet — Sie können sie für die nächste Buchung verwenden.',
        'pass_not_restored' => 'Die Reitkarte (falls verwendet) wurde nicht zurückerstattet — Stornierung außerhalb der Frist.',
    ],

    'booking_reminder' => [
        'subject' => 'Erinnerung: morgen :time — :tenant',
        'line_intro' => 'Wir erinnern an Ihre morgige Buchung.',
        'cancel_policy' => 'Falls Sie stornieren müssen, tun Sie dies schnellstmöglich — eine Stornierung bis :hours Stunden vor der Reitstunde ist kostenfrei.',
        'line_signoff' => 'Bis morgen!',
    ],

    'booking_requested' => [
        'subject' => 'Anfrage erhalten — :tenant',
        'line_intro' => 'Vielen Dank für Ihre Buchungsanfrage im Reitstall **:tenant**.',
        'line_processing' => 'Der Reitstall bestätigt die Buchung per E-Mail (in der Regel innerhalb weniger Stunden) und teilt ein Pferd zu.',
        'line_pass_warning' => 'Falls Sie nicht fristgerecht stornieren, wird die Reitkarte (falls verwendet) eingelöst.',
    ],

    'booking_rescheduled' => [
        'subject' => 'Buchung verschoben — :tenant',
        'line_intro' => 'Ihre Buchung im Reitstall **:tenant** wurde verschoben.',
        'line_undo' => 'Falls die Verschiebung ein Versehen war, können Sie stornieren und einen neuen Termin buchen.',
        'portal_link' => 'Verwalten Sie Buchungen im Kundenportal: [:url](:url)',
    ],

    'client_portal_magic_link' => [
        'subject' => 'Anmeldung beim Kundenportal — :tenant',
        'line_intro' => 'Klicken Sie unten, um sich im Kundenportal **:tenant** anzumelden.',
        'action' => 'Anmelden',
        'line_ttl' => 'Der Link ist :minutes Minuten gültig und kann nur einmal verwendet werden.',
        'line_security' => 'Wenn Sie sich nicht angemeldet haben, ignorieren Sie diese Nachricht.',
    ],

    'horse_message' => [
        'subject_default' => 'Neue Nachricht — :horse — :tenant',
        'subject_with_subject' => ':subject (:horse)',
        'line_intro' => 'Sie haben eine neue Nachricht zum Pferd **:horse** (:tenant) erhalten.',
        'attachments_one' => '📎 1 Anhang',
        'attachments_many' => '📎 :count Anhänge',
        'action' => 'Nachricht öffnen',
    ],

    'invoice_issued' => [
        'subject' => ':kind :number — :tenant',
        'line_intro' => 'Wir haben :kind **:number** vom Reitstall **:tenant** ausgestellt.',
        'action_pay' => 'Rechnung ansehen und bezahlen',
        'action_view' => 'Rechnung ansehen',
        'line_offline_payment' => 'Bitte überweisen Sie den Betrag auf das Konto des Reitstalls — Details finden Sie im Kundenportal.',
        'line_thanks' => 'Vielen Dank!',
    ],

    'new_booking_request' => [
        'subject' => 'Neue Online-Anfrage — :tenant',
        'line_intro' => 'Ein Kunde hat eine Reitstunden-Anfrage im Reitstall **:tenant** gestellt:',
        'client_format' => ':name (:email)',
        'client_format_with_phone' => ':name (:email, Tel. :phone)',
        'line_action_required' => 'Zum Bestätigen wechseln Sie zur Buchungsbearbeitung, weisen Sie ein Pferd zu und ändern Sie den Status auf „Bestätigt".',
        'action' => 'Buchung öffnen',
        'line_horse_assignment' => 'Das Pferd kann erst beim Bestätigen zugewiesen werden — das System verlangt dies vor der Statusänderung.',
        'salutation' => '— Hovera',
    ],

    'user_invitation' => [
        'subject_with_tenant' => 'Einladung in den Reitstall :tenant — Hovera',
        'subject_default' => 'Einladung zu Hovera',
        'line_with_tenant' => 'Sie wurden dem Reitstall **:tenant** im System Hovera:role hinzugefügt.',
        'line_with_tenant_role' => ' mit der Rolle *:role*',
        'line_default' => 'Sie haben eine Einladung zum System Hovera erhalten.',
        'line_setup' => 'Um Ihr Konto zu aktivieren und ein Passwort festzulegen, klicken Sie unten.',
        'action' => 'Passwort festlegen und anmelden',
        'line_expires' => 'Der Link läuft am :date (UTC) ab.',
        'line_security' => 'Falls Sie das nicht waren, können Sie diese Nachricht ignorieren — ohne Klick wird das Konto nicht aktiviert.',
        'salutation' => '— Hovera',
    ],
];
