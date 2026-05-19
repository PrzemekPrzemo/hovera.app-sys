<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Publiczne centrum pomocy — dostępne BEZ logowania pod /help.
 *
 * Te same markdownowe instrukcje co w panelu (/app/help) + osadzone
 * dokumenty prawne. Cel: external linki do dokumentacji (np. ze stopki
 * publicznej, z signupu, z partnerów) bez zmuszania do zakładania konta.
 *
 * Trasy:
 *   /help                  → owner, manual view (default)
 *   /help/{persona}        → persona w {owner, employee, specialist, client}
 *   /help/legal            → wszystkie 3 dokumenty (akordion)
 *   /help/legal/{doc}      → single doc — terms / privacy / dpa
 *
 * Content per locale (PL/EN/DE/FR/RU) — z fallbackiem PL.
 */
class HelpController extends Controller
{
    /** @var list<string> */
    private const SUPPORTED_LOCALES = ['pl', 'en', 'de', 'fr', 'ru'];

    /**
     * Persona keys — używane do walidacji URL `{persona}` segment'u + jako
     * lookup do pliku markdown `resources/help/{locale}/{persona}.md`.
     *
     * `transporter` dodany po PR #228 (dodał `resources/help/{locale}/transporter.md`)
     * + PR #250 (publiczna rejestracja `/przewoznicy/dolacz` — nowi
     * transporter'zy potrzebują dedykowanej dokumentacji od day-1).
     *
     * @var list<string>
     */
    public const PERSONAS = ['owner', 'employee', 'specialist', 'client', 'transporter'];

    /** @var list<string> */
    public const LEGAL_DOCS = ['terms', 'privacy', 'dpa'];

    public function show(Request $request, ?string $persona = null): View
    {
        $persona = in_array($persona, self::PERSONAS, true) ? $persona : 'owner';
        $locale = $this->useLocale();

        $path = resource_path("help/{$locale}/{$persona}.md");
        if (! File::exists($path)) {
            $path = resource_path("help/pl/{$persona}.md");
        }

        return view('public.help.show', [
            'activeView' => 'manual',
            'activePersona' => $persona,
            'helpHtml' => Str::markdown((string) File::get($path), ['html_input' => 'allow']),
            'personas' => $this->personaCards(),
            'legalDocuments' => null,
            'singleDoc' => null,
            'pageTitle' => __('pages.help.persona.'.$persona),
        ]);
    }

    public function legal(Request $request, ?string $doc = null): View
    {
        $doc = in_array($doc, self::LEGAL_DOCS, true) ? $doc : null;

        return view('public.help.show', [
            'activeView' => 'legal',
            'activePersona' => 'owner',
            'helpHtml' => null,
            'personas' => $this->personaCards(),
            'legalDocuments' => $this->legalDocuments(),
            'singleDoc' => $doc,
            'pageTitle' => $doc !== null ? __('public/legal.'.$doc.'.title') : __('pages.help.tab.legal'),
        ]);
    }

    /**
     * @return list<array{key: string, label: string, description: string, icon: string}>
     */
    private function personaCards(): array
    {
        $out = [];
        foreach (self::PERSONAS as $key) {
            $out[] = [
                'key' => $key,
                'label' => (string) __('pages.help.persona.'.$key),
                'description' => (string) __('pages.help.persona.'.$key.'_desc'),
                'icon' => match ($key) {
                    'owner' => 'storefront',
                    'employee' => 'users',
                    'specialist' => 'heart',
                    'client' => 'identification',
                    'transporter' => 'truck',
                    default => 'storefront',
                },
            ];
        }

        return $out;
    }

    /**
     * @return list<array{key: string, title: string, intro: string, sections: list<array{heading: string, body: string}>}>
     */
    private function legalDocuments(): array
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

    private function useLocale(): string
    {
        $locale = app()->getLocale();

        return in_array($locale, self::SUPPORTED_LOCALES, true) ? $locale : 'pl';
    }
}
