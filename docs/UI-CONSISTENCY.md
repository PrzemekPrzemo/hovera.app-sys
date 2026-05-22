# UI Consistency Guidelines

Krótkie, wymierne reguły dla nowych zmian w UI Hovera. Cel: spójny look&feel
między panelami (admin/app/transport/owner) i przewidywalne doświadczenie
użytkownika.

## Ikony

Single source of truth: **`app/Support/UiIcons.php`**.

- Każdy entity ma JEDEN icon w całym systemie. `Horse` = `heroicon-o-bolt`
  wszędzie (lista, formularz, nav, breadcrumb).
- Dla nowego entity dodaj `public const NAME = 'heroicon-o-...';` w `UiIcons`
  zanim zaczniesz go używać.
- Variant `outline` (`heroicon-o-*`) jest domyślny (Filament convention).
  Solid (`heroicon-s-*`) tylko dla badge columns i hero CTAs.

```php
// dobrze
use App\Support\UiIcons;

protected static ?string $navigationIcon = UiIcons::CLIENT;
```

## Notifications

Każdy `Notification::make()` ma:

1. `->title('Krótki nagłówek')` — co się stało
2. `->body('Szczegóły / next step')` — JAK użytkownik ma zareagować
3. `->success() / ->danger() / ->warning() / ->info()` — wskazany typ

Wyjątek: `->info()` może mieć tylko title gdy to czysto informacyjne (np. "Skopiowano do schowka").

```php
// dobrze
Notification::make()
    ->title(__('common.gus_lookup.invalid_nip'))
    ->body(__('common.gus_lookup.invalid_nip_hint'))
    ->danger()
    ->send();
```

## Empty states

Tabele Filament: gdy lista jest pusta, zamiast generycznego "No records found":

1. `emptyStateHeading(__('...'))` — co użytkownik zobaczy
2. `emptyStateDescription(__('...'))` — dlaczego jest puste, co zrobić
3. `emptyStateActions([CreateAction::make()])` — primary CTA

Alternatywa: dashboard QuickStartWidget już pełni rolę guide'a — empty state na tabeli rezerwuj dla rzadkich przypadków gdy user wszedł bezpośrednio na resource bez przejścia przez dashboard.

## Loading states

Buttony długo-trwających operacji (KSeF send, NBP fetch, GUS/VIES lookup, file export):

- Filament Forms\Components\Actions\Action: automatycznie pokazuje spinner
- Custom buttons: użyj `wire:loading` / `wire:target` w blade widoku
- Banner / toast: zmień label na `__('common.actions.loading')` podczas wykonania

## Język i tłumaczenia

- Każda widoczna dla użytkownika string przechodzi przez `__('...')`. Nigdy hardcoded PL/EN w `*.php` / blade.
- Klucze i18n grupowane po module: `app/<feature>.php`, `transport/<feature>.php`, `owner/<feature>.php`, `common.php` dla wspólnych.
- 5 locale: `pl` (primary), `en`, `de`, `fr`, `ru`. PL+EN zawsze synchronicznie; DE/FR/RU mogą być częściowe (fallback do PL działa automatycznie).

## Spójne kolory

- `primary` — brand (amber/gold, RGB ok. `#A8956B`). Używać dla głównych CTA, linków akcji.
- `success` — green-600. Tylko dla potwierdzeń (FV opłacona, KSeF zaakceptowany).
- `warning` — amber-500. Dla "trzeba zwrócić uwagę" (FV po terminie, niski stan paliwa).
- `danger` — red-600. Tylko dla błędów blokujących i destructive actions (Delete, Cancel).
- `gray` — secondary actions (Cancel, Close, Skip).

## Mobile

- Pierwsze 360px width (małe Androidy) musi działać na każdej stronie.
- Tabele: `responsive(['md'])` dla kolumn drugorzędnych — chowane na mobile.
- Formularze: kolumny `2/3` zamieniają się w `1` na mobile.
- Nav: drawer (sandwich) automatycznie po `md` breakpoint — Filament default.

## Audit checklist przed merge

- [ ] Każdy nowy widok ma title (`getTitle()`) + breadcrumb
- [ ] Każda nowa wiadomość użytkownika idzie przez `__('...')`
- [ ] Każda nowa ikona pochodzi z `UiIcons::*` (lub dodana do tej klasy)
- [ ] Każdy `Notification::danger/warning()` ma `body()`
- [ ] Każdy long-running button ma loading state
- [ ] Tablica nowego resource ma sensowny empty state LUB dashboard QuickStart pokrywa flow
- [ ] Mobile sanity check w chrome devtools (360px width)
