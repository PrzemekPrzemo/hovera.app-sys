<?php

declare(strict_types=1);

namespace App\Filament\Transport\Pages;

use App\Tenancy\TenantManager;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Pierwsze logowanie transporter owner'a → 3-krokowy wizard.
 *
 *   Krok 1 — Dane firmy + dokumenty PWL (CTA do /transport/transporter-documents)
 *   Krok 2 — Strefy działania + cennik (CTA do /transport/service-areas + settings)
 *   Krok 3 — KSeF cert (CTA do /transport/transport-settings → tab KSeF)
 *
 * Patrz docs/ROLE-MATRIX.md i resources/help/pl/transporter.md
 * (sekcje „Dokumenty PWL" / „KSeF — krok po kroku").
 */
class OnboardingWizard extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.transport.pages.onboarding-wizard';

    /** @var array<string, mixed> */
    public array $data = [];

    public function getTitle(): string|Htmlable
    {
        return __('transport/onboarding.title');
    }

    public static function canAccess(): bool
    {
        return true;
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Wizard::make([
                    Forms\Components\Wizard\Step::make(__('transport/onboarding.steps.documents.title'))
                        ->description(__('transport/onboarding.steps.documents.description'))
                        ->icon('heroicon-o-document-check')
                        ->schema([
                            Forms\Components\Placeholder::make('documents_info')
                                ->label('')
                                ->content(new HtmlString(
                                    '<div class="text-sm space-y-2">'
                                    .'<p>'.e(__('transport/onboarding.steps.documents.body')).'</p>'
                                    .'<p class="font-medium">'.e(__('transport/onboarding.steps.documents.cta_hint')).'</p>'
                                    .'<a href="/transport/transporter-documents" class="text-primary-600 hover:underline" target="_blank">→ '.e(__('transport/onboarding.steps.documents.cta')).'</a>'
                                    .'</div>'
                                )),
                        ]),

                    Forms\Components\Wizard\Step::make(__('transport/onboarding.steps.coverage.title'))
                        ->description(__('transport/onboarding.steps.coverage.description'))
                        ->icon('heroicon-o-map')
                        ->schema([
                            Forms\Components\Placeholder::make('coverage_info')
                                ->label('')
                                ->content(new HtmlString(
                                    '<div class="text-sm space-y-2">'
                                    .'<p>'.e(__('transport/onboarding.steps.coverage.body')).'</p>'
                                    .'<div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-3">'
                                    .'<a href="/transport/service-areas" class="rounded-lg border border-gray-200 px-3 py-2 text-sm hover:border-primary-500 hover:bg-primary-50">→ '.e(__('transport/onboarding.steps.coverage.cta_areas')).'</a>'
                                    .'<a href="/transport/transport-settings" class="rounded-lg border border-gray-200 px-3 py-2 text-sm hover:border-primary-500 hover:bg-primary-50">→ '.e(__('transport/onboarding.steps.coverage.cta_pricing')).'</a>'
                                    .'</div>'
                                    .'</div>'
                                )),
                        ]),

                    Forms\Components\Wizard\Step::make(__('transport/onboarding.steps.ksef.title'))
                        ->description(__('transport/onboarding.steps.ksef.description'))
                        ->icon('heroicon-o-shield-check')
                        ->schema([
                            Forms\Components\Placeholder::make('ksef_info')
                                ->label('')
                                ->content(new HtmlString(
                                    '<div class="text-sm space-y-2">'
                                    .'<p>'.e(__('transport/onboarding.steps.ksef.body')).'</p>'
                                    .'<p class="font-medium">'.e(__('transport/onboarding.steps.ksef.cta_hint')).'</p>'
                                    .'<a href="/transport/transport-settings" class="text-primary-600 hover:underline" target="_blank">→ '.e(__('transport/onboarding.steps.ksef.cta')).'</a>'
                                    .'</div>'
                                )),
                        ]),
                ])
                    ->submitAction(new HtmlString(
                        '<button type="button" wire:click="finish" class="fi-btn fi-btn-color-primary inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold bg-primary-600 text-white hover:bg-primary-500">'
                        .e(__('transport/onboarding.action.finish'))
                        .'</button>'
                    )),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('skip')
                ->label(__('transport/onboarding.action.skip'))
                ->color('gray')
                ->action('skip'),
        ];
    }

    public function finish(): void
    {
        $tenant = app(TenantManager::class)->tenantOrFail();
        $tenant->markOnboardingFinished('completed');

        Notification::make()
            ->title(__('transport/onboarding.notify.completed_title'))
            ->body(__('transport/onboarding.notify.completed_body'))
            ->success()
            ->send();

        $this->redirect('/transport');
    }

    public function skip(): void
    {
        $tenant = app(TenantManager::class)->tenantOrFail();
        $tenant->markOnboardingFinished('skipped');

        Notification::make()
            ->title(__('transport/onboarding.notify.skipped_title'))
            ->body(__('transport/onboarding.notify.skipped_body'))
            ->info()
            ->send();

        $this->redirect('/transport');
    }
}
