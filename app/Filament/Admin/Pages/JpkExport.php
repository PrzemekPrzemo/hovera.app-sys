<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\Central\Tenant;
use App\Services\Ksef\JpkFa3Exporter;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Master-admin: ad-hoc eksport JPK_FA(3) dla dowolnego tenanta.
 *
 * UI dla księgowego po stronie hovery — wybierasz tenanta (stajnia /
 * transporter), rok i opcjonalnie kwartał, klikasz "Pobierz" — dostajesz
 * plik XML do downloadu. Pod spodem wywołuje `JpkFa3Exporter::exportRange`
 * z TenantManager switching na DB stajni.
 *
 * Sam tenant nie ma do tego dostępu — JPK żądany przez US trafia do
 * księgowego (najczęściej po stronie hovery jeśli używają naszego BO),
 * stąd admin panel a nie /app panel.
 */
class JpkExport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static ?int $navigationSort = 60;

    protected static string $view = 'filament.admin.pages.jpk-export';

    /** @var array<string,mixed> */
    public array $data = [
        'tenant_id' => null,
        'year' => null,
        'quarter' => null,
    ];

    public static function getNavigationLabel(): string
    {
        return __('admin/jpk_export.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.finances');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin/jpk_export.title');
    }

    public function mount(): void
    {
        $this->form->fill([
            'tenant_id' => null,
            'year' => (int) now()->year,
            'quarter' => null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make(__('admin/jpk_export.form.section'))
                    ->columns(3)
                    ->description(__('admin/jpk_export.form.description'))
                    ->schema([
                        Forms\Components\Select::make('tenant_id')
                            ->label(__('admin/jpk_export.form.tenant'))
                            ->options(fn () => Tenant::query()
                                ->whereIn('status', Tenant::PANEL_ACCESSIBLE_STATUSES)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\Select::make('year')
                            ->label(__('admin/jpk_export.form.year'))
                            ->options($this->yearOptions())
                            ->required(),
                        Forms\Components\Select::make('quarter')
                            ->label(__('admin/jpk_export.form.quarter'))
                            ->options([
                                '' => __('admin/jpk_export.form.quarter_full_year'),
                                '1' => 'Q1 (I–III)',
                                '2' => 'Q2 (IV–VI)',
                                '3' => 'Q3 (VII–IX)',
                                '4' => 'Q4 (X–XII)',
                            ])
                            ->helperText(__('admin/jpk_export.form.quarter_helper')),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label(__('admin/jpk_export.action.download'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->action(fn () => $this->download()),
        ];
    }

    public function download(): ?StreamedResponse
    {
        $state = $this->form->getState();

        /** @var Tenant|null $tenant */
        $tenant = Tenant::query()->find($state['tenant_id'] ?? null);
        if ($tenant === null) {
            Notification::make()
                ->danger()
                ->title(__('admin/jpk_export.notify.tenant_missing'))
                ->send();

            return null;
        }

        $year = (int) ($state['year'] ?? now()->year);
        $quarterRaw = (string) ($state['quarter'] ?? '');
        $quarter = $quarterRaw !== '' ? (int) $quarterRaw : null;

        try {
            $xml = $quarter !== null
                ? app(JpkFa3Exporter::class)->exportQuarter($tenant, $year, $quarter)
                : app(JpkFa3Exporter::class)->exportYear($tenant, $year);
        } catch (Throwable $e) {
            report($e);
            Notification::make()
                ->danger()
                ->title(__('admin/jpk_export.notify.failed'))
                ->body($e->getMessage())
                ->send();

            return null;
        }

        $filename = sprintf(
            'JPK_FA-%s-%d%s.xml',
            $tenant->slug,
            $year,
            $quarter !== null ? '-Q'.$quarter : '',
        );

        return response()->streamDownload(
            fn () => print $xml,
            $filename,
            [
                'Content-Type' => 'application/xml',
                'Content-Length' => strlen($xml),
            ],
        );
    }

    /**
     * @return array<int,string>
     */
    private function yearOptions(): array
    {
        $currentYear = (int) now()->year;
        $options = [];
        // Backfill 5 lat wstecz + bieżący rok (KSeF od 2024, więc 5 lat
        // pokrywa wszystkie sensowne przypadki audytowe).
        for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
            $options[$y] = (string) $y;
        }

        return $options;
    }
}
