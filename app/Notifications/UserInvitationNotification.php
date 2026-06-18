<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Central\UserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * `ShouldQueue` — synchroniczny SMTP send blokował signup flow'y
 * (transporter onboarding, team invites). SMTP handshake + body delivery
 * to typowo 10-30s; mnożone przez liczbę zaproszonych = łatwe 504.
 * Soft-fail pattern w `SendInvitation` zostaje — gdy queue dispatch
 * sam padnie, invitation row jest w DB i admin może resend.
 */
class UserInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly UserInvitation $invitation,
        public readonly string $plaintextToken,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $tenantName = $this->invitation->tenant?->name;
        $url = url('/invite/'.$this->plaintextToken);

        $message = (new MailMessage)
            ->subject($tenantName
                ? __('notifications.user_invitation.subject_with_tenant', ['tenant' => $tenantName])
                : __('notifications.user_invitation.subject_default'))
            ->greeting($this->invitation->name
                ? __('notifications.common.greeting_named', ['name' => $this->invitation->name])
                : __('notifications.common.greeting'));

        if ($tenantName) {
            $rolePart = $this->invitation->role
                ? __('notifications.user_invitation.line_with_tenant_role', ['role' => $this->invitation->role])
                : '';
            $message->line(__('notifications.user_invitation.line_with_tenant', [
                'tenant' => $tenantName,
                'role' => $rolePart,
            ]));
        } else {
            $message->line(__('notifications.user_invitation.line_default'));
        }

        return $message
            ->line(__('notifications.user_invitation.line_setup'))
            ->action(__('notifications.user_invitation.action'), $url)
            ->line(__('notifications.user_invitation.line_expires', ['date' => $this->invitation->expires_at->format('Y-m-d H:i')]))
            ->line(__('notifications.user_invitation.line_security'))
            ->salutation(__('notifications.user_invitation.salutation'));
    }
}
