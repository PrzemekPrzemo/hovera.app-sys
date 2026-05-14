<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

/**
 * Help / centrum pomocy — instrukcja obsługi per rola + dokumentacja
 * prawna. Treść markdown w resources/help/{locale}/{persona}.md
 * (PL / EN / DE / FR / RU). Legal sekcje czytają lang/{locale}/public/legal.php.
 *
 * 7 ról tenant → 4 persony:
 *   owner / admin / manager → owner
 *   vet                     → specialist
 *   instructor / employee / viewer → employee
 *   (klient stajni ma osobny help w portalu klienta)
 */
class Help extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?int $navigationSort = 99;

    /** @var list<string> */
    private const SUPPORTED_LOCALES = ['pl', 'en', 'de', 'fr', 'ru'];

    /** @var list<string> */
    public const PERSONAS = ['owner', 'employee', 'specialist', 'client'];

    /** @var list<string> */
    public const LEGAL_DOCS = ['terms', 'privacy', 'dpa'];

    #[Url(as: 'p')]
    public string $persona = '';

    #[Url(as: 'view')]
    public string $activeView = 'manual';

    public function mount(): void
    {
        if ($this->persona === '' || ! in_array($this->persona, self::PERSONAS, true)) {
            $this->persona = $this->defaultPersonaForCurrentUser();
        }
        if (! in_array($this->activeView, ['manual', 'legal'], true)) {
            $this->activeView = 'manual';
        }
    }

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

    public function switchPersona(string $persona): void
    {
        if (in_array($persona, self::PERSONAS, true)) {
            $this->persona = $persona;
            $this->activeView = 'manual';
        }
    }

    public function switchView(string $view): void
    {
        if (in_array($view, ['manual', 'legal'], true)) {
            $this->activeView = $view;
        }
    }

    public function helpHtml(): string
    {
        $locale = $this->useLocale();
        $persona = in_array($this->persona, self::PERSONAS, true) ? $this->persona : 'owner';

        $path = resource_path("help/{$locale}/{$persona}.md");
        if (! File::exists($path)) {
            $path = resource_path("help/pl/{$persona}.md");
        }
        if (! File::exists($path)) {
            $path = resource_path('help/pl/owner.md');
        }

        return Str::markdown((string) File::get($path), ['html_input' => 'allow']);
    }

    /**
     * @return list<array{key: string, label: string, icon: string, illustration: string}>
     */
    public function personaCards(): array
    {
        return [
            [
                'key' => 'owner',
                'label' => __('pages.help.persona.owner'),
                'description' => __('pages.help.persona.owner_desc'),
                'icon' => 'heroicon-o-building-storefront',
                'illustration' => asset('img/help/role-owner.svg'),
            ],
            [
                'key' => 'employee',
                'label' => __('pages.help.persona.employee'),
                'description' => __('pages.help.persona.employee_desc'),
                'icon' => 'heroicon-o-user-group',
                'illustration' => asset('img/help/role-employee.svg'),
            ],
            [
                'key' => 'specialist',
                'label' => __('pages.help.persona.specialist'),
                'description' => __('pages.help.persona.specialist_desc'),
                'icon' => 'heroicon-o-heart',
                'illustration' => asset('img/help/role-specialist.svg'),
            ],
            [
                'key' => 'client',
                'label' => __('pages.help.persona.client'),
                'description' => __('pages.help.persona.client_desc'),
                'icon' => 'heroicon-o-identification',
                'illustration' => asset('img/help/role-client.svg'),
            ],
        ];
    }

    /**
     * @return list<array{key: string, title: string, intro: string, sections: list<array{heading: string, body: string}>}>
     */
    public function legalDocuments(): array
    {
        $out = [];
        foreach (self::LEGAL_DOCS as $doc) {
            $sections = [];
            for ($i = 1; $i <= 11; $i++) {
                $headingKey = "public/legal.{$doc}.section_{$i}_heading";
                $bodyKey = "public/legal.{$doc}.section_{$i}_body";
                $heading = __($headingKey);
                if ($heading === $headingKey) {
                    break;
                }
                $sections[] = [
                    'heading' => (string) $heading,
                    'body' => (string) __($bodyKey),
                ];
            }
            $out[] = [
                'key' => $doc,
                'title' => (string) __("public/legal.{$doc}.title"),
                'intro' => (string) __("public/legal.{$doc}.intro"),
                'sections' => $sections,
            ];
        }

        return $out;
    }

    public function legalLastUpdated(): string
    {
        return (string) __('public/legal.last_updated');
    }

    public function legalLastUpdatedLabel(): string
    {
        return (string) __('public/legal.last_updated_label');
    }

    public function activePersona(): string
    {
        return in_array($this->persona, self::PERSONAS, true) ? $this->persona : 'owner';
    }

    private function useLocale(): string
    {
        $locale = app()->getLocale();

        return in_array($locale, self::SUPPORTED_LOCALES, true) ? $locale : 'pl';
    }

    private function defaultPersonaForCurrentUser(): string
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
