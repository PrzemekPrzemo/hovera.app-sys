<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Help / instrukcja obsługi — dostępna dla każdego zalogowanego
 * użytkownika w panelu /app. Treść w resources/help/{locale}/{role}.md
 * (PL / EN / DE / FR), renderowana przez Str::markdown().
 *
 * Wariant per-rola: owner / admin / manager → owner.md, vet → specialist.md,
 * instructor / employee / viewer → employee.md. Z fallbackiem do owner.md.
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

        $variant = $this->variantForCurrentUser();

        $path = resource_path("help/{$useLocale}/{$variant}.md");
        if (! File::exists($path)) {
            $path = resource_path("help/pl/{$variant}.md");
        }
        if (! File::exists($path)) {
            $path = resource_path('help/pl/owner.md');
        }

        $markdown = (string) File::get($path);

        // Str::markdown returns HTML — Filament view wraps in prose styles.
        return Str::markdown($markdown, ['html_input' => 'allow']);
    }

    private function variantForCurrentUser(): string
    {
        $tenant = Filament::getTenant();
        $user = Auth::user();
        if (! $tenant || ! $user) {
            return 'owner';
        }

        $role = (string) ($tenant->memberships()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->value('role') ?? 'owner');

        return match ($role) {
            'owner', 'admin', 'manager' => 'owner',
            'vet' => 'specialist',
            'instructor', 'employee', 'viewer' => 'employee',
            default => 'owner',
        };
    }
}
