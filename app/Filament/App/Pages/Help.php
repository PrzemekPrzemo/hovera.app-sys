<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Help / instrukcja obsługi — dostępna dla każdego zalogowanego
 * użytkownika w panelu /app. Treść w resources/help/{locale}/owner.md
 * (PL / EN / DE / FR), renderowana przez Str::markdown().
 *
 * Wybór języka idzie z App::getLocale() (per-user preference z
 * SetLocale middleware), z fallbackiem do PL gdy plik dla aktualnego
 * locale nie istnieje.
 */
class Help extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?int $navigationSort = 99;

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('pages.help.navigation');
    }

    public function getTitle(): string|Htmlable
    {
        return __('pages.help.title');
    }

    protected static string $view = 'filament.app.pages.help';

    public function helpHtml(): string
    {
        $locale = app()->getLocale();
        $supported = ['pl', 'en', 'de', 'fr'];
        $useLocale = in_array($locale, $supported, true) ? $locale : 'pl';

        $path = resource_path("help/{$useLocale}/owner.md");
        if (! File::exists($path)) {
            $path = resource_path('help/pl/owner.md');
        }

        $markdown = (string) File::get($path);

        // Str::markdown returns HTML — Filament view wraps in prose styles.
        return Str::markdown($markdown, ['html_input' => 'allow']);
    }
}
