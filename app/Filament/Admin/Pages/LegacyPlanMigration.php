<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Services\Billing\LegacyPlanMigrator;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Master-admin: lista tenantów wciąż siedzących na transport_*_legacy
 * planach + 1-click migracja na rekomendowany nowy plan (mapowanie
 * w `LegacyPlanMigrator::MAPPING`).
 *
 * Bez tej strony admin musiałby ręcznie tinkerem. Operacja jest
 * sensytywna (cena IDZIE W GÓRĘ, klient musi być powiadomiony) — UI
 * wymusza confirmation modal z polem "Powód" oraz wysyła email do
 * owner'a tenanta.
 *
 * Dostępne tylko dla `is_master_admin = true`.
 */
class LegacyPlanMigration extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?int $navigationSort = 31;

    protected static string $view = 'filament.admin.pages.legacy-plan-migration';

    protected static ?string $slug = 'legacy-plan-migration';

    public static function getNavigationLabel(): string
    {
        return __('admin/plan.migration.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.configuration');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin/plan.migration.title');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user !== null && (bool) ($user->is_master_admin ?? false);
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
    }

    public function getSubheading(): ?string
    {
        return __('admin/plan.migration.intro');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->tenantsQuery())
            ->emptyStateHeading(__('admin/plan.migration.no_legacy'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin/plan.migration.column.tenant'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('plan.code')
                    ->label(__('admin/plan.migration.column.current_plan'))
                    ->badge()
                    ->color('danger'),
                Tables\Columns\TextColumn::make('recommended_plan_code')
                    ->label(__('admin/plan.migration.column.recommended_plan'))
                    ->badge()
                    ->color('success')
                    ->getStateUsing(function (Tenant $r): string {
                        $recommended = $this->migrator()->recommendedNewPlan($r);

                        return $recommended?->code ?? '—';
                    }),
                Tables\Columns\TextColumn::make('price_change')
                    ->label(__('admin/plan.migration.column.price_change'))
                    ->getStateUsing(function (Tenant $r): string {
                        $old = $r->plan;
                        $new = $this->migrator()->recommendedNewPlan($r);
                        if ($old === null || $new === null) {
                            return '—';
                        }
                        $oldP = (int) ($old->price_monthly_cents ?? 0);
                        $newP = (int) ($new->price_monthly_cents ?? 0);
                        $currency = strtoupper((string) ($new->currency ?? 'PLN'));

                        return number_format($oldP / 100, 0, ',', ' ')
                            .' → '
                            .number_format($newP / 100, 0, ',', ' ')
                            .' '.$currency;
                    }),
                Tables\Columns\IconColumn::make('stripe_subscription_id')
                    ->label(__('admin/plan.migration.column.has_stripe'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin/plan.migration.column.status'))
                    ->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('legacy_plan')
                    ->label(__('admin/plan.table.column.legacy_badge'))
                    ->options(fn () => array_combine(
                        LegacyPlanMigrator::legacyCodes(),
                        LegacyPlanMigrator::legacyCodes(),
                    ))
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }
                        $planId = Plan::where('code', $data['value'])->value('id');

                        return $planId !== null
                            ? $query->where('plan_id', $planId)
                            : $query;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('migrate')
                    ->label(__('admin/plan.migration.action.migrate'))
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('warning')
                    ->modalHeading(__('admin/plan.migration.action.migrate_modal_heading'))
                    ->modalDescription(__('admin/plan.migration.action.migrate_modal_description'))
                    ->modalSubmitActionLabel(__('admin/plan.migration.action.submit'))
                    ->form([
                        Forms\Components\Select::make('effective')
                            ->label(__('admin/plan.migration.action.effective_label'))
                            ->options([
                                'next_cycle' => __('admin/plan.migration.action.effective_next_cycle'),
                                'immediate' => __('admin/plan.migration.action.effective_immediate'),
                            ])
                            ->default('next_cycle')
                            ->required(),
                        Forms\Components\Textarea::make('reason')
                            ->label(__('admin/plan.migration.action.reason_label'))
                            ->placeholder(__('admin/plan.migration.action.reason_placeholder'))
                            ->maxLength(500),
                        Forms\Components\Toggle::make('send_email')
                            ->label(__('admin/plan.migration.action.send_email_label'))
                            ->default(true),
                    ])
                    ->action(function (Tenant $record, array $data): void {
                        $this->performMigration(
                            tenant: $record,
                            effective: (string) ($data['effective'] ?? 'next_cycle'),
                            reason: $data['reason'] ?? null,
                            sendEmail: (bool) ($data['send_email'] ?? true),
                        );
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_migrate')
                    ->label(__('admin/plan.migration.action.bulk_label'))
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Select::make('effective')
                            ->label(__('admin/plan.migration.action.effective_label'))
                            ->options([
                                'next_cycle' => __('admin/plan.migration.action.effective_next_cycle'),
                                'immediate' => __('admin/plan.migration.action.effective_immediate'),
                            ])
                            ->default('next_cycle')
                            ->required(),
                        Forms\Components\Toggle::make('send_email')
                            ->label(__('admin/plan.migration.action.send_email_label'))
                            ->default(true),
                    ])
                    ->action(function ($records, array $data): void {
                        $count = 0;
                        foreach ($records as $tenant) {
                            $changed = $this->performMigration(
                                tenant: $tenant,
                                effective: (string) ($data['effective'] ?? 'next_cycle'),
                                reason: 'bulk_admin_migration',
                                sendEmail: (bool) ($data['send_email'] ?? true),
                                silent: true,
                            );
                            if ($changed) {
                                $count++;
                            }
                        }
                        Notification::make()
                            ->success()
                            ->title(__('admin/plan.migration.action.bulk_summary', ['count' => $count]))
                            ->send();
                    }),
            ]);
    }

    private function performMigration(
        Tenant $tenant,
        string $effective,
        ?string $reason,
        bool $sendEmail,
        bool $silent = false,
    ): bool {
        $migrator = $this->migrator();
        $newPlan = $migrator->recommendedNewPlan($tenant);

        if ($newPlan === null) {
            if (! $silent) {
                Notification::make()
                    ->danger()
                    ->title(__('admin/plan.migration.action.no_recommendation_title'))
                    ->body(__('admin/plan.migration.action.no_recommendation_body'))
                    ->send();
            }

            return false;
        }

        try {
            $result = $migrator->migrate($tenant, $newPlan, $effective, $reason, $sendEmail);
        } catch (\Throwable $e) {
            if (! $silent) {
                Notification::make()
                    ->danger()
                    ->title(__('admin/plan.migration.action.error_title'))
                    ->body($e->getMessage())
                    ->send();
            }

            return false;
        }

        if (! $result['changed']) {
            if (! $silent) {
                Notification::make()
                    ->warning()
                    ->title(__('admin/plan.migration.action.noop_title'))
                    ->body(__('admin/plan.migration.action.noop_body'))
                    ->send();
            }

            return false;
        }

        if (! $silent) {
            Notification::make()
                ->success()
                ->title(__('admin/plan.migration.action.success_title'))
                ->body(__('admin/plan.migration.action.success_body', [
                    'old' => (string) $result['old_plan_code'],
                    'new' => $result['new_plan_code'],
                    'stripe' => $result['stripe_updated'] ? 'updated' : 'skipped',
                ]))
                ->send();
        }

        return true;
    }

    /**
     * Tenanty których plan kod jest jednym z legacy kodów. Pobieramy
     * ID planów raz; query trzeba zawsze odświeżyć żeby refresh stronie
     * po migracji usuwał już zmigrowane tenanty.
     */
    protected function tenantsQuery(): Builder
    {
        $legacyIds = Plan::query()
            ->whereIn('code', LegacyPlanMigrator::legacyCodes())
            ->pluck('id');

        return Tenant::query()
            ->with('plan')
            ->whereIn('plan_id', $legacyIds);
    }

    private function migrator(): LegacyPlanMigrator
    {
        return app(LegacyPlanMigrator::class);
    }
}
