<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

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
 * Pierwsze logowanie stable owner'a → 3-krokowy wizard.
 *
 *   Krok 1 — Dane firmy (CTA do GUS lookup w /app/settings/tenant)
 *   Krok 2 — KSeF cert (CTA do /app/ksef-settings; skip OK do 2026-02)
 *   Krok 3 — Pierwszy klient / pierwszy koń (CTA do resource'ów)
 *
 * Wszystkie kroki informacyjne + CTA — wizard nie wymusza działań, tylko
 * pokazuje "co powinieneś zrobić jako pierwszy stable user".
 *
 * Finish → markOnboardingFinished('completed'); Skip → 'skipped'. Oba
 * uznawane przez RedirectToOnboarding middleware jako "wizard zamknięty".
 */
class OnboardingWizard extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static string $view = 'filament.app.pages.onboarding-wizard';

    /** @var array<string, mixed> */
    public array $data = [];

    public function getTitle(): string|Htmlable
    {
        return __('app/onboarding.title');
    }

    public static function canAccess(): bool
    {
        return true;
    }

    /**
     * Link w sidebar pokazujemy DOPÓKI user nie ukończył / nie pominął
     * wizarda — po deferred (silent) wciąż widać link, żeby user mógł
     * wrócić i dokończyć. Po explicit completed/skipped znika.
     */
    public static function shouldRegisterNavigation(): bool
    {
        $tenant = app(TenantManager::class)->current();
        if (! $tenant) {
            return false;
        }

        return ! $tenant->isOnboardingFinished();
    }

    public static function getNavigationLabel(): string
    {
        return __('app/onboarding.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.settings');
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public function mount(): void
    {
        // Pierwsze otwarcie wizarda → silent `deferred_at`. Od tej chwili
        // middleware nie redirectuje już z innych stron, user ma pełen
        // dostęp do systemu. Banner na dashboardzie zachęca do dokończenia.
        $tenant = app(TenantManager::class)->current();
        if ($tenant !== null && ! $tenant->wasOnboardingShown()) {
            $tenant->markOnboardingFinished('deferred');
        }

        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Wizard::make([
                    Forms\Components\Wizard\Step::make(__('app/onboarding.steps.company.title'))
                        ->description(__('app/onboarding.steps.company.description'))
                        ->icon('heroicon-o-building-office-2')
                        ->schema([
                            Forms\Components\Placeholder::make('company_info')
                                ->label('')
                                ->content(new HtmlString(
                                    '<div class="text-sm space-y-2">'
                                    .'<p>'.e(__('app/onboarding.steps.company.body')).'</p>'
                                    .'<p class="font-medium">'.e(__('app/onboarding.steps.company.cta_hint')).'</p>'
                                    .'<a href="/app/settings/tenant" class="text-primary-600 hover:underline" target="_blank">→ '.e(__('app/onboarding.steps.company.cta')).'</a>'
                                    .'</div>'
                                )),
                        ]),

                    Forms\Components\Wizard\Step::make(__('app/onboarding.steps.ksef.title'))
                        ->description(__('app/onboarding.steps.ksef.description'))
                        ->icon('heroicon-o-shield-check')
                        ->schema([
                            Forms\Components\Placeholder::make('ksef_info')
                                ->label('')
                                ->content(new HtmlString(
                                    '<div class="text-sm space-y-2">'
                                    .'<p>'.e(__('app/onboarding.steps.ksef.body')).'</p>'
                                    .'<p class="font-medium">'.e(__('app/onboarding.steps.ksef.cta_hint')).'</p>'
                                    .'<a href="/app/ksef-settings" class="text-primary-600 hover:underline" target="_blank">→ '.e(__('app/onboarding.steps.ksef.cta')).'</a>'
                                    .'</div>'
                                )),
                        ]),

                    Forms\Components\Wizard\Step::make(__('app/onboarding.steps.first_record.title'))
                        ->description(__('app/onboarding.steps.first_record.description'))
                        ->icon('heroicon-o-rocket-launch')
                        ->schema([
                            Forms\Components\Placeholder::make('first_record_info')
                                ->label('')
                                ->content(new HtmlString(
                                    '<div class="text-sm space-y-2">'
                                    .'<p>'.e(__('app/onboarding.steps.first_record.body')).'</p>'
                                    .'<div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-3">'
                                    .'<a href="/app/clients/create" class="rounded-lg border border-gray-200 px-3 py-2 text-sm hover:border-primary-500 hover:bg-primary-50">→ '.e(__('app/onboarding.steps.first_record.cta_client')).'</a>'
                                    .'<a href="/app/horses/create" class="rounded-lg border border-gray-200 px-3 py-2 text-sm hover:border-primary-500 hover:bg-primary-50">→ '.e(__('app/onboarding.steps.first_record.cta_horse')).'</a>'
                                    .'</div>'
                                    .'</div>'
                                )),
                        ]),
                ])
                    ->submitAction(new HtmlString(
                        '<button type="button" wire:click="finish" class="fi-btn fi-btn-color-primary inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold bg-primary-600 text-white hover:bg-primary-500">'
                        .e(__('app/onboarding.action.finish'))
                        .'</button>'
                    )),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('skip')
                ->label(__('app/onboarding.action.skip'))
                ->color('gray')
                ->action('skip'),
        ];
    }

    public function finish(): void
    {
        $tenant = app(TenantManager::class)->tenantOrFail();
        $tenant->markOnboardingFinished('completed');

        Notification::make()
            ->title(__('app/onboarding.notify.completed_title'))
            ->body(__('app/onboarding.notify.completed_body'))
            ->success()
            ->send();

        $this->redirect('/app');
    }

    public function skip(): void
    {
        $tenant = app(TenantManager::class)->tenantOrFail();
        $tenant->markOnboardingFinished('skipped');

        Notification::make()
            ->title(__('app/onboarding.notify.skipped_title'))
            ->body(__('app/onboarding.notify.skipped_body'))
            ->info()
            ->send();

        $this->redirect('/app');
    }
}
