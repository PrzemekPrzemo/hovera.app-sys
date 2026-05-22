<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Services\Admin\HealthCheckService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Master-admin: status integracji zewnętrznych (GUS, CEIDG, VIES, NBP,
 * KSeF central, SMTP, DB central).
 *
 * Snapshot (instant) przy mount — pokazuje co skonfigurowane, co nie.
 * Live ping na żądanie przez akcje per integracja (rate-limited zdrowym
 * rozsądkiem, max ~3s per ping).
 *
 * Dla operacyjności: gdy KSeF padnie / NBP timeout / GUS API key wygasł
 * — master admin widzi to natychmiast tutaj zamiast czekać na zgłoszenie
 * od klienta.
 */
class HealthChecks extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?int $navigationSort = 90;

    protected static string $view = 'filament.admin.pages.health-checks';

    /**
     * @var list<array{key:string, label:string, status:string, detail:?string}>
     */
    public array $rows = [];

    public static function getNavigationLabel(): string
    {
        return __('admin/health_checks.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.configuration');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin/health_checks.title');
    }

    public function mount(): void
    {
        $this->refreshSnapshot();
    }

    public function refreshSnapshot(): void
    {
        $this->rows = app(HealthCheckService::class)->snapshot();
    }

    protected function getHeaderActions(): array
    {
        $svc = app(HealthCheckService::class);

        return [
            Action::make('refresh_all')
                ->label(__('admin/health_checks.action.refresh_all'))
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->refreshSnapshot()),

            Action::make('ping_gus')
                ->label(__('admin/health_checks.action.ping_gus'))
                ->icon('heroicon-o-bolt')
                ->color('gray')
                ->action(function () use ($svc) {
                    $result = $svc->gusStatus(live: true);
                    $this->updateRow('gus', $result);
                    $this->notifyFromResult($result);
                }),

            Action::make('ping_ceidg')
                ->label(__('admin/health_checks.action.ping_ceidg'))
                ->icon('heroicon-o-bolt')
                ->color('gray')
                ->action(function () use ($svc) {
                    $result = $svc->ceidgStatus(live: true);
                    $this->updateRow('ceidg', $result);
                    $this->notifyFromResult($result);
                }),

            Action::make('ping_vies')
                ->label(__('admin/health_checks.action.ping_vies'))
                ->icon('heroicon-o-bolt')
                ->color('gray')
                ->action(function () use ($svc) {
                    $result = $svc->viesStatus(live: true);
                    $this->updateRow('vies', $result);
                    $this->notifyFromResult($result);
                }),
        ];
    }

    /**
     * @param  array{key:string, label:string, status:string, detail:?string}  $newRow
     */
    private function updateRow(string $key, array $newRow): void
    {
        foreach ($this->rows as $i => $row) {
            if ($row['key'] === $key) {
                $this->rows[$i] = $newRow;

                return;
            }
        }
    }

    /**
     * @param  array{key:string, label:string, status:string, detail:?string}  $result
     */
    private function notifyFromResult(array $result): void
    {
        $notif = Notification::make()->title($result['label']);

        match ($result['status']) {
            'ok' => $notif->success()->body($result['detail'] ?? ''),
            'degraded' => $notif->warning()->body($result['detail'] ?? ''),
            'error' => $notif->danger()->body($result['detail'] ?? ''),
            default => $notif->info()->body($result['detail'] ?? ''),
        };

        $notif->send();
    }
}
