<?php

declare(strict_types=1);

namespace App\Notifications\Specialist;

use App\Models\Central\ExternalSpecialist;
use App\Models\Central\SpecialistMagicLink;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invitation email do external specialist z magic link initial_setup
 * URL. Plain token zawarty TYLKO w tej notyfikacji (DB ma tylko hash) —
 * po wysyłce mail token nie żyje już nigdzie.
 *
 * Per captured decisions §3 — 7d expiry, setup wymaga password + email
 * verification code (drugi mail w follow-up).
 */
class SpecialistInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ExternalSpecialist $specialist,
        public readonly SpecialistMagicLink $magicLink,
        public readonly string $plainToken,
        public readonly string $invitingTenantName,
        public readonly string $invitingUserName,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $setupUrl = url('/specialist/setup/'.$this->plainToken);
        $expiresIn = $this->magicLink->expires_at->diffForHumans(now(), syntax: CarbonInterface::DIFF_RELATIVE_TO_NOW);

        return (new MailMessage)
            ->subject(__('specialist/invite.mail.subject', ['tenant' => $this->invitingTenantName]))
            ->greeting(__('specialist/invite.mail.greeting', ['name' => $this->specialist->display_name]))
            ->line(__('specialist/invite.mail.intro', [
                'tenant' => $this->invitingTenantName,
                'user' => $this->invitingUserName,
            ]))
            ->line(__('specialist/invite.mail.what_is_it'))
            ->action(__('specialist/invite.mail.cta'), $setupUrl)
            ->line(__('specialist/invite.mail.expiry', ['when' => $expiresIn]))
            ->line(__('specialist/invite.mail.security_note'))
            ->salutation(__('specialist/invite.mail.salutation'));
    }
}
