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
];
