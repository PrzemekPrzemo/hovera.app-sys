<?php

declare(strict_types=1);

namespace App\Notifications\Owner;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notyfikuje horse_owner gdy przewoźnik wysłał ofertę w odpowiedzi na
 * jego zapytanie transportowe.
 *
 * Dispatch z `QuoteResource::sendQuote()` w panelu /transport gdy
 * `Quote.originator_tenant_type === 'horse_owner'`. Owner widzi
 * notyfikację w in-app bell + dostaje mail z linkiem do panelu (gdzie
 * w `/owner/transport-orders/{id}` ma listę ofert z PR #395).
 *
 * Kierowane przez `OwnerNotificationDispatcher::forCentralUser()` —
 * resolveuje User po `central_user_id` originator'a leadu.
 */
class QuoteSentForOwnerNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $transporterTenantId,
        public readonly string $transporterName,
        public readonly string $quoteNumber,
        public readonly int $priceGrossCents,
        public readonly string $currency,
        public readonly ?string $proposedDate,
        public readonly string $pickupAddress,
        public readonly string $dropoffAddress,
        public readonly string $publicLandingUrl,
        public readonly ?string $orderPanelUrl,
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
            'kind' => 'owner.quote_sent',
            'transporter_tenant_id' => $this->transporterTenantId,
            'transporter_name' => $this->transporterName,
            'quote_number' => $this->quoteNumber,
            'price_gross_cents' => $this->priceGrossCents,
            'currency' => $this->currency,
            'proposed_date' => $this->proposedDate,
            'pickup_address' => $this->pickupAddress,
            'dropoff_address' => $this->dropoffAddress,
            'url' => $this->orderPanelUrl ?: $this->publicLandingUrl,
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $priceFormatted = number_format($this->priceGrossCents / 100, 2, ',', ' ').' '.$this->currency;

        $msg = (new MailMessage)
            ->subject(__('notifications.owner_quote_sent.subject', [
                'transporter' => $this->transporterName,
            ]))
            ->greeting(__('notifications.common.greeting'))
            ->line(__('notifications.owner_quote_sent.line_intro', [
                'transporter' => $this->transporterName,
            ]))
            ->line('**'.__('notifications.owner_quote_sent.field.route').':** '
                .$this->pickupAddress.' → '.$this->dropoffAddress)
            ->line('**'.__('notifications.owner_quote_sent.field.price').':** '.$priceFormatted);

        if ($this->proposedDate !== null) {
            $msg->line('**'.__('notifications.owner_quote_sent.field.date').':** '.$this->proposedDate);
        }

        $msg->action(__('notifications.owner_quote_sent.action_accept'), $this->publicLandingUrl);

        if ($this->orderPanelUrl !== null) {
            $msg->line(__('notifications.owner_quote_sent.line_panel', [
                'url' => $this->orderPanelUrl,
            ]));
        }

        return $msg;
    }
}
