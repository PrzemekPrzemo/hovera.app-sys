<?php

declare(strict_types=1);

namespace App\Domain\Transport\Notifications;

use App\Models\Central\Tenant;
use App\Models\Tenant\TransporterDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mail do owner'a 30 dni przed `expires_at` wymaganego dokumentu PWL.
 * Idzie przez mailer 'transport'.
 *
 * Idempotencja po stronie command'u: w tabeli `transporter_document_notifications_log`
 * trzymamy (document_id, type) → wysłany. Tu tylko renderujemy treść.
 */
class TransporterDocumentExpiringSoonNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly TransporterDocument $document,
        public readonly int $daysToExpiry,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $typeLabel = $this->document->document_type?->label() ?? 'document';
        $expiresAt = $this->document->expires_at?->format('Y-m-d') ?? '—';

        return (new MailMessage)
            ->mailer('transport')
            ->subject(__('transport/documents.expiry_notify.subject', [
                'type' => $typeLabel,
                'days' => $this->daysToExpiry,
            ]))
            ->greeting(__('transport/documents.expiry_notify.greeting'))
            ->line(__('transport/documents.expiry_notify.intro', [
                'type' => $typeLabel,
                'name' => $this->tenant->name,
                'date' => $expiresAt,
                'days' => $this->daysToExpiry,
            ]))
            ->line(__('transport/documents.expiry_notify.cta'))
            ->action(
                __('transport/documents.expiry_notify.action'),
                url('/transport/transporter-documents'),
            );
    }
}
