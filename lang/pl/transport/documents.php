<?php

declare(strict_types=1);

return [
    'navigation' => 'Weryfikacja konta',
    'title' => 'Dokumenty weryfikacyjne',

    'status' => [
        'heading' => 'Status weryfikacji konta',
        'pending_body' => 'Aby aktywować konto, musisz wgrać :count brakujących dokumentów. Bez weryfikacji nie wystawisz ofert ani faktur.',
        'under_review_body' => 'Wszystkie wymagane dokumenty wgrane — weryfikacja przez zespół hovera w toku (zwykle 1–2 dni robocze).',
        'verified_body' => 'Konto aktywne. Możesz wystawiać oferty, faktury, otrzymywać zapytania z marketplace\'u.',
        'rejected_body' => 'Konto odrzucone. Sprawdź uwagi w poszczególnych dokumentach i prześlij poprawione wersje.',
        'missing_badge' => ':count brak',
    ],

    'label' => [
        'required' => 'wymagany',
        'optional' => 'opcjonalny',
        'uploaded_at' => 'Wgrany',
        'expires_at' => 'Ważny do',
        'issued_at' => 'Data wystawienia',
        'expired' => 'WYGASŁ',
        'expiring_soon' => 'wygasa wkrótce',
        'rejection_reason' => 'Powód odrzucenia',
    ],

    'action' => [
        'upload' => 'Wgraj',
        'delete' => 'Usuń',
    ],

    'confirm' => [
        'delete' => 'Usunąć ten dokument? Tej akcji nie można cofnąć.',
    ],

    'notify' => [
        'uploaded' => 'Dokument wgrany',
        'deleted' => 'Dokument usunięty',
        'error' => 'Błąd',
    ],

    'error' => [
        'no_file' => 'Wybierz plik przed kliknięciem „Wgraj".',
        'bad_mime' => 'Niedozwolony format pliku. Dozwolone: :allowed.',
        'too_large' => 'Plik zbyt duży. Maksymalnie :limit.',
    ],

    'footer' => [
        'allowed_formats' => 'Akceptujemy: PDF, JPG, PNG. Maksymalnie 10 MB per plik. Wszystkie pliki przechowywane są w szyfrowanym storage UE.',
    ],

    // PLW = Przewóz Wewnątrzwspólnotowy Zwierząt Żywych. Sekcja na liście
    // dokumentów odróżniająca legacy slots (KRS, dawne) od ścisłego zestawu PLW.
    'section' => [
        'pwl_required' => 'Dokumenty PLW (wymagane do weryfikacji)',
        'pwl_optional' => 'Dokumenty opcjonalne',
        'legacy' => 'Dokumenty legacy (nie zaliczają się do PLW)',
    ],

    'helper' => [
        'pwl_authorization_choice' => 'Wybierz Typ 1 LUB Typ 2 — zależnie od profilu transportów. Typ 2 (> 8h) pokrywa również Typ 1.',
        'pwl_vehicle_per_vehicle' => 'Dokument wystawiany per pojazd. Jeśli masz flotę, wgraj scaloną wersję PDF dla wszystkich pojazdów.',
        'wash_log_period' => 'Wgrywaj na bieżąco — wpisy starsze niż 12 miesięcy są traktowane jako nieaktualne.',
    ],

    'checklist' => [
        'heading' => 'Lista wymaganych dokumentów PLW',
        'progress' => ':done z :total dokumentów zweryfikowanych',
        'missing_intro' => 'Brakuje:',
        'all_complete' => 'Wszystkie wymagane dokumenty zatwierdzone.',
        'pwl_authorization_alternative' => 'Zezwolenie PLW (Typ 1 LUB Typ 2)',
    ],

    'admin' => [
        'verify_doc' => 'Zatwierdź dokument',
        'reject_doc' => 'Odrzuć dokument',
        'verify_doc_confirm' => 'Zatwierdzić ten dokument? Po zatwierdzeniu nie można go już usunąć przez transportera.',
        'rejection_reason_required' => 'Powód odrzucenia (widoczny dla transportera)',
        'notify_doc_verified' => 'Dokument zatwierdzony',
        'notify_doc_rejected' => 'Dokument odrzucony',
        'cannot_verify_tenant' => 'Najpierw zweryfikuj wszystkie wymagane dokumenty PLW (:done/:total). Zobacz listę poniżej.',
    ],

    'expiry_notify' => [
        'subject' => 'Dokument :type wygasa za :days dni',
        'greeting' => 'Cześć!',
        'intro' => 'Dokument „:type" w koncie firmy :name wygasa :date (za :days dni).',
        'cta' => 'Wgraj nowy w panelu — inaczej Twoje konto może zostać tymczasowo zawieszone w momencie wygaśnięcia.',
        'action' => 'Otwórz dokumenty',
    ],
];
