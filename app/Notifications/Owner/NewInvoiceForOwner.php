<?php

declare(strict_types=1);

namespace App\Notifications\Owner;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notyfikuje właściciela gdy stajnia wystawi fakturę (status: draft →
 * issued). Dispatch z Invoice model event (Observer). Owner widzi
 * fakturę przez `OwnerInvoiceFeedService` (Faza 3) — link prowadzi
 * do InvoiceShow page.
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 6 PR 6.1".
 */
class NewInvoiceForOwner extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $stableTenantId,
        public readonly string $stableName,
        public readonly string $invoiceId,
        public readonly ?string $invoiceNumber,
        public readonly int $totalCents,
        public readonly string $currency,
        public readonly ?string $dueAt,
        public readonly ?string $billingPeriod,
        public readonly ?string $horseName,
        public readonly string $ownerPanelUrl,
    ) {}

    /** @return array<int,string> */
    public function via(mixed $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array<string,mixed> */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'kind' => 'owner.new_invoice',
            'stable_tenant_id' => $this->stableTenantId,
            'stable_name' => $this->stableName,
            'invoice_id' => $this->invoiceId,
            'invoice_number' => $this->invoiceNumber,
            'total_cents' => $this->totalCents,
            'currency' => $this->currency,
            'due_at' => $this->dueAt,
            'billing_period' => $this->billingPeriod,
            'horse_name' => $this->horseName,
            'url' => $this->ownerPanelUrl,
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $totalFormatted = number_format($this->totalCents / 100, 2, ',', ' ').' '.$this->currency;

        $subject = $this->invoiceNumber !== null
            ? __('notifications.owner_new_invoice.subject_with_number', [
                'number' => $this->invoiceNumber,
                'stable' => $this->stableName,
            ])
            : __('notifications.owner_new_invoice.subject_default', [
                'stable' => $this->stableName,
            ]);

        $msg = (new MailMessage)
            ->subject($subject)
            ->greeting(__('notifications.common.greeting'))
            ->line(__('notifications.owner_new_invoice.line_intro', [
                'stable' => $this->stableName,
            ]));

        if ($this->invoiceNumber !== null) {
            $msg->line('**'.__('notifications.owner_new_invoice.field.number').':** '.$this->invoiceNumber);
        }
        if ($this->billingPeriod !== null) {
            $msg->line('**'.__('notifications.owner_new_invoice.field.period').':** '.$this->billingPeriod);
        }
        if ($this->horseName !== null) {
            $msg->line('**'.__('notifications.owner_new_invoice.field.horse').':** '.$this->horseName);
        }
        $msg->line('**'.__('notifications.owner_new_invoice.field.total').':** '.$totalFormatted);
        if ($this->dueAt !== null) {
            $msg->line('**'.__('notifications.owner_new_invoice.field.due_at').':** '.$this->dueAt);
        }

        return $msg->action(__('notifications.owner_new_invoice.action'), $this->ownerPanelUrl);
    }
}
