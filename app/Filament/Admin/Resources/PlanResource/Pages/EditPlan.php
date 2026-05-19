<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PlanResource\Pages;

use App\Filament\Admin\Resources\PlanResource;
use App\Models\Central\Plan;
use App\Services\Billing\StripeProductCreator;
use App\Services\MasterAuditLogger;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('stripe_wizard')
                ->label(__('admin/plan.action.stripe_wizard.label'))
                ->icon('heroicon-o-bolt')
                ->color('success')
                ->visible(function (): bool {
                    /** @var Plan $record */
                    $record = $this->getRecord();

                    // Schowaj gdy już stworzono — operacja jest one-shot,
                    // nie chcemy stworzyć duplikatu w Stripe.
                    return ! ($record->stripe_price_monthly_id !== null
                        && $record->stripe_price_monthly_id !== '');
                })
                ->requiresConfirmation()
                ->modalHeading(__('admin/plan.action.stripe_wizard.modal_heading'))
                ->modalDescription(__('admin/plan.action.stripe_wizard.modal_description'))
                ->modalSubmitActionLabel(__('admin/plan.action.stripe_wizard.modal_submit'))
                ->action(function (StripeProductCreator $creator, MasterAuditLogger $audit): void {
                    /** @var Plan $record */
                    $record = $this->getRecord();

                    try {
                        $result = $creator->createForPlan($record);
                    } catch (\RuntimeException $e) {
                        $this->reportWizardException($e);

                        return;
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title(__('admin/plan.action.stripe_wizard.error_title'))
                            ->body($e->getMessage())
                            ->send();

                        return;
                    }

                    $audit->record(
                        action: 'plan.stripe_created',
                        targetType: Plan::class,
                        targetId: (string) $record->id,
                        payload: [
                            'plan_code' => $record->code,
                            'product_id' => $result['product_id'],
                            'currencies' => array_keys($result['prices']),
                            'prices' => $result['prices'],
                        ],
                    );

                    Notification::make()
                        ->success()
                        ->title(__('admin/plan.action.stripe_wizard.success_title'))
                        ->body(__('admin/plan.action.stripe_wizard.success_body', [
                            'id' => $result['product_id'],
                            'currencies' => implode(', ', array_keys($result['prices'])),
                        ]))
                        ->send();

                    // Refresh formularza żeby admin zobaczył uzupełnione
                    // stripe_price_*_id w polach.
                    $this->refreshFormData([
                        'stripe_price_monthly_id',
                        'stripe_price_yearly_id',
                        'prices_per_currency',
                    ]);
                }),
            Actions\DeleteAction::make(),
        ];
    }

    private function reportWizardException(\RuntimeException $e): void
    {
        match ($e->getMessage()) {
            'plan.stripe.enterprise_skipped' => Notification::make()
                ->warning()
                ->title(__('admin/plan.action.stripe_wizard.enterprise_title'))
                ->body(__('admin/plan.action.stripe_wizard.enterprise_body'))
                ->send(),
            'plan.stripe.already_created' => Notification::make()
                ->warning()
                ->title(__('admin/plan.action.stripe_wizard.already_created_title'))
                ->body(__('admin/plan.action.stripe_wizard.already_created_body'))
                ->send(),
            'plan.stripe.missing_api_key' => Notification::make()
                ->danger()
                ->title(__('admin/plan.action.stripe_wizard.missing_key_title'))
                ->body(__('admin/plan.action.stripe_wizard.missing_key_body'))
                ->send(),
            default => Notification::make()
                ->danger()
                ->title(__('admin/plan.action.stripe_wizard.error_title'))
                ->body($e->getMessage())
                ->send(),
        };
    }
}
