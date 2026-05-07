<?php

declare(strict_types=1);

namespace App\Filament\Components;

use Filament\Forms\Components\TextInput;

/**
 * Helper do pól ceny w Filament — input PLN, DB cents.
 *
 * Wszystkie kolumny `*_cents` w bazie trzymają wartości jako int (grosze)
 * — to standard księgowy (brak floating-point precision issues, łatwa
 * arytmetyka). Ale w UI pokazujemy "249.00 PLN" bo nikt normalny nie
 * wpisuje "24900".
 *
 * Użycie:
 *   PriceInput::make('price_monthly_cents')->label('Cena miesięczna')
 *
 * Konwersja:
 *   - DB → form (formatStateUsing): cents / 100 → "249.00"
 *   - form → DB (dehydrateStateUsing): "249.00" → 24900
 *
 * Float jest tylko stage'em UI — nigdy nie trafia do DB.
 */
class PriceInput
{
    public static function make(string $name, ?string $label = null): TextInput
    {
        return TextInput::make($name)
            ->label($label ?? 'Cena')
            ->numeric()
            ->step(0.01)
            ->minValue(0)
            ->prefix('PLN')
            ->inputMode('decimal')
            ->dehydrateStateUsing(fn ($state) => self::toCents($state))
            ->formatStateUsing(fn (?int $state) => self::fromCents($state));
    }

    /**
     * "249.00" / "249,00" / 249.5 / null → 24900 / 24950 / null
     */
    public static function toCents(mixed $state): ?int
    {
        if ($state === null || $state === '') {
            return null;
        }

        // Akceptuj zarówno "249.00" jak i "249,00" (PL format)
        if (is_string($state)) {
            $state = str_replace([' ', ','], ['', '.'], $state);
        }

        return (int) round((float) $state * 100);
    }

    /**
     * 24900 / null → "249.00" / null
     */
    public static function fromCents(?int $state): ?string
    {
        if ($state === null) {
            return null;
        }

        return number_format($state / 100, 2, '.', '');
    }
}
