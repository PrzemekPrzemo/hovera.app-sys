<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Apn\ApnMessage;

class NewInvoice extends Notification
{
    use Queueable;

    public function __construct(public readonly Invoice $invoice) {}

    public function via(object $notifiable): array
    {
        return ['apn', 'fcm'];
    }

    public function toApn(object $notifiable): ApnMessage
    {
        return ApnMessage::create()
            ->title(__('notifications.invoice.title'))
            ->body($this->subtitle())
            ->custom('kind', 'invoice')
            ->custom('invoice_id', (string) $this->invoice->id);
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'notification' => [
                'title' => __('notifications.invoice.title'),
                'body' => $this->subtitle(),
            ],
            'data' => ['kind' => 'invoice', 'invoice_id' => (string) $this->invoice->id],
        ];
    }

    private function subtitle(): string
    {
        $cents = (int) ($this->invoice->amount_cents ?? 0);
        $currency = (string) ($this->invoice->currency ?? 'PLN');

        return sprintf('%s · %.2f %s', (string) ($this->invoice->number ?? '-'), $cents / 100, $currency);
    }
}
