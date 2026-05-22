<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

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
 * Pierwsze logowanie horse owner'a → 3-krokowy wizard.
 *
 *   Krok 1 — Mój pierwszy koń (CTA → /owner/horses/create)
 *   Krok 2 — Ulubieni przewoźnicy (opcjonalne; CTA → /owner/favorite-transporters)
 *   Krok 3 — Pierwsze zamówienie transportu (CTA → /owner/order-transport)
 *
 * FREE tier — bez ról, bez KSeF, bez bramki płatności. Patrz
 * resources/help/pl/horse_owner.md.
 */
class OnboardingWizard extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.owner.pages.onboarding-wizard';

    /** @var array<string, mixed> */
    public array $data = [];

    public function getTitle(): string|Htmlable
    {
        return __('owner/onboarding.title');
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
                    Forms\Components\Wizard\Step::make(__('owner/onboarding.steps.horse.title'))
                        ->description(__('owner/onboarding.steps.horse.description'))
                        ->icon('heroicon-o-star')
                        ->schema([
                            Forms\Components\Placeholder::make('horse_info')
                                ->label('')
                                ->content(new HtmlString(
                                    '<div class="text-sm space-y-2">'
                                    .'<p>'.e(__('owner/onboarding.steps.horse.body')).'</p>'
                                    .'<p class="font-medium">'.e(__('owner/onboarding.steps.horse.cta_hint')).'</p>'
                                    .'<a href="/owner/horses/create" class="text-primary-600 hover:underline" target="_blank">→ '.e(__('owner/onboarding.steps.horse.cta')).'</a>'
                                    .'</div>'
                                )),
                        ]),

                    Forms\Components\Wizard\Step::make(__('owner/onboarding.steps.favorites.title'))
                        ->description(__('owner/onboarding.steps.favorites.description'))
                        ->icon('heroicon-o-heart')
                        ->schema([
                            Forms\Components\Placeholder::make('favorites_info')
                                ->label('')
                                ->content(new HtmlString(
                                    '<div class="text-sm space-y-2">'
                                    .'<p>'.e(__('owner/onboarding.steps.favorites.body')).'</p>'
                                    .'<p class="text-xs text-gray-500">'.e(__('owner/onboarding.steps.favorites.optional')).'</p>'
                                    .'<a href="/owner/favorite-transporters" class="text-primary-600 hover:underline" target="_blank">→ '.e(__('owner/onboarding.steps.favorites.cta')).'</a>'
                                    .'</div>'
                                )),
                        ]),

                    Forms\Components\Wizard\Step::make(__('owner/onboarding.steps.first_order.title'))
                        ->description(__('owner/onboarding.steps.first_order.description'))
                        ->icon('heroicon-o-truck')
                        ->schema([
                            Forms\Components\Placeholder::make('first_order_info')
                                ->label('')
                                ->content(new HtmlString(
                                    '<div class="text-sm space-y-2">'
                                    .'<p>'.e(__('owner/onboarding.steps.first_order.body')).'</p>'
                                    .'<p class="font-medium">'.e(__('owner/onboarding.steps.first_order.cta_hint')).'</p>'
                                    .'<a href="/owner/order-transport" class="text-primary-600 hover:underline" target="_blank">→ '.e(__('owner/onboarding.steps.first_order.cta')).'</a>'
                                    .'</div>'
                                )),
                        ]),
                ])
                    ->submitAction(new HtmlString(
                        '<button type="button" wire:click="finish" class="fi-btn fi-btn-color-primary inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold bg-primary-600 text-white hover:bg-primary-500">'
                        .e(__('owner/onboarding.action.finish'))
                        .'</button>'
                    )),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('skip')
                ->label(__('owner/onboarding.action.skip'))
                ->color('gray')
                ->action('skip'),
        ];
    }

    public function finish(): void
    {
        $tenant = app(TenantManager::class)->tenantOrFail();
        $tenant->markOnboardingFinished('completed');

        Notification::make()
            ->title(__('owner/onboarding.notify.completed_title'))
            ->body(__('owner/onboarding.notify.completed_body'))
            ->success()
            ->send();

        $this->redirect('/owner');
    }

    public function skip(): void
    {
        $tenant = app(TenantManager::class)->tenantOrFail();
        $tenant->markOnboardingFinished('skipped');

        Notification::make()
            ->title(__('owner/onboarding.notify.skipped_title'))
            ->body(__('owner/onboarding.notify.skipped_body'))
            ->info()
            ->send();

        $this->redirect('/owner');
    }
}
