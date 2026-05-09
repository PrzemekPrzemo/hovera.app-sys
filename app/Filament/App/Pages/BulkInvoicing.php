<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Actions\Invoicing\GenerateBulkBoardingInvoices;
use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

/**
 * Operator-driven monthly bulk invoicing — preview FV draft for every
 * boarder for the chosen month, then click "Generate" to persist drafts.
 * Owner finalizes (Issue + KSeF + email) per-invoice in the standard
 * /app/invoices flow.
 */
class BulkInvoicing extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 45;

    protected static string $view = 'filament.app.pages.bulk-invoicing';

    #[Url]
    public ?string $month = null;

    /** @var array<int, string> */
    public array $selected = [];

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.finances');
    }

    public static function getNavigationLabel(): string
    {
        return __('pages.bulk_invoicing.navigation');
    }

    public function getTitle(): string|Htmlable
    {
        return __('pages.bulk_invoicing.title');
    }

    public function monthStart(): Carbon
    {
        $key = $this->month ?? now()->subMonth()->format('Y-m');
        if (! preg_match('/^\d{4}-\d{2}$/', $key)) {
            $key = now()->subMonth()->format('Y-m');
        }

        return Carbon::createFromFormat('Y-m-d', $key.'-01')->startOfMonth();
    }

    public function monthLabel(): string
    {
        return $this->monthStart()->translatedFormat('LLLL Y');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function preview(): array
    {
        $tenant = app(TenantManager::class)->current();
        if (! $tenant instanceof Tenant) {
            return [];
        }

        return app(GenerateBulkBoardingInvoices::class)->preview($tenant, $this->monthStart());
    }

    public function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ').' zł';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label(__('pages.bulk_invoicing.actions.generate'))
                ->color('primary')
                ->icon('heroicon-m-rectangle-stack')
                ->requiresConfirmation()
                ->modalHeading(__('pages.bulk_invoicing.confirm.heading'))
                ->modalDescription(__('pages.bulk_invoicing.confirm.description', ['month' => $this->monthLabel()]))
                ->modalSubmitActionLabel(__('pages.bulk_invoicing.confirm.submit'))
                ->action(function () {
                    $tenant = app(TenantManager::class)->current();
                    if (! $tenant instanceof Tenant) {
                        return;
                    }

                    $clientIds = $this->selected;
                    if (empty($clientIds)) {
                        // Fall back to all clients with items in the preview.
                        $clientIds = array_column($this->preview(), 'client_id');
                    }

                    $created = app(GenerateBulkBoardingInvoices::class)
                        ->execute($tenant, $this->monthStart(), $clientIds);

                    Notification::make()
                        ->title(__('pages.bulk_invoicing.flash.success', ['count' => count($created)]))
                        ->success()
                        ->send();
                }),
        ];
    }
}
