<?php

declare(strict_types=1);

namespace App\Notifications\Stable;

use App\Models\Central\Tenant;
use App\Models\Tenant\BoxInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mail do owner'a stajni gdy ktoś z zewnątrz wypełni formularz "Zapytaj
 * o boks" — przychodzi przez `/s/{slug}/box-inquiry`. Treść zawiera
 * dane kontaktowe + ile koni + kiedy chce zacząć — żeby stable mógł
 * jednym mailem odpowiedzieć lub od razu zadzwonić.
 */
class BoxInquiryReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly BoxInquiry $inquiry,
        public readonly Tenant $tenant,
    ) {}

    /** @return array<int,string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $msg = (new MailMessage)
            ->subject(__('notifications.box_inquiry.subject', [
                'name' => $this->inquiry->name,
                'tenant' => $this->tenant->name,
            ]))
            ->greeting(__('notifications.box_inquiry.greeting'))
            ->line(__('notifications.box_inquiry.intro', [
                'name' => $this->inquiry->name,
                'count' => $this->inquiry->horse_count,
            ]))
            ->line('**'.__('notifications.box_inquiry.field.email').':** '.$this->inquiry->email);

        if ($this->inquiry->phone) {
            $msg->line('**'.__('notifications.box_inquiry.field.phone').':** '.$this->inquiry->phone);
        }
        if ($this->inquiry->preferred_from) {
            $msg->line('**'.__('notifications.box_inquiry.field.preferred_from').':** '
                .$this->inquiry->preferred_from->format('Y-m-d'));
        }
        if ($this->inquiry->message) {
            $msg->line('**'.__('notifications.box_inquiry.field.message').':**')
                ->line($this->inquiry->message);
        }

        return $msg
            ->action(
                __('notifications.box_inquiry.cta'),
                url('/app/box-inquiries'),
            )
            ->line(__('notifications.box_inquiry.signoff'));
    }
}
