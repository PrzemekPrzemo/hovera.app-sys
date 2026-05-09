<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Filament\Concerns\RestrictedByTenantRole;
use App\Services\Tenancy\TenantRoleGate;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Sidebar entry for "Subskrypcja hovera" — links into the Blade-based
 * billing flow (kept outside Filament so we can redirect to Stripe
 * Checkout / Customer Portal without Livewire ceremony).
 *
 * The page itself is a thin wrapper: mount() redirects straight to
 * the BillingController route. Filament still registers the nav item.
 */
class Billing extends Page
{
    use RestrictedByTenantRole;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 90;

    protected static string $view = 'filament.pages.billing-redirect';

    protected static function allowedRoles(): array
    {
        return TenantRoleGate::FULL_ADMINS;
    }

    public static function getNavigationLabel(): string
    {
        return __('billing.navigation.label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.settings');
    }

    public function getTitle(): string|Htmlable
    {
        return __('billing.page.title');
    }

    public function mount(): void
    {
        // Hard redirect — billing lives in plain Blade so the Stripe
        // redirect chain doesn't fight Livewire's wire:navigate.
        redirect()->route('billing.show')->send();
    }
}
