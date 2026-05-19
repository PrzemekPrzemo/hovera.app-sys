<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Email do owner'a tenanta po migracji z legacy planu na nową ofertę
 * (LegacyPlanMigrator). Wymagane prawnie — docs/TRANSPORT.md §15 lock-in
 * mówi że klient musi być powiadomiony o zmianie ceny i ma 12-mc gwarancję
 * niezmienności od dziś.
 *
 * Mailer 'default' (queue domyślne) — wysyłka tylko po sukcesie zmiany
 * `plan_id` w DB.
 */
class TenantPlanMigratedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $tenantName,
        public readonly string $oldPlanName,
        public readonly string $newPlanName,
        public readonly string $newPriceFormatted,
        public readonly string $effective,
        public readonly Carbon $lockInUntil,
    ) {}

    /** @return array<int,string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mail/tenant_plan_migrated.subject'))
            // Markdown — register `mail::` namespace przy renderze.
            ->markdown('emails.tenant-plan-migrated', [
                'tenantName' => $this->tenantName,
                'oldPlanName' => $this->oldPlanName,
                'newPlanName' => $this->newPlanName,
                'newPriceFormatted' => $this->newPriceFormatted,
                'effective' => $this->effective,
                'lockInUntil' => $this->lockInUntil,
            ]);
    }
}
