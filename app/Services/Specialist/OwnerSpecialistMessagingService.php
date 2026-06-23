<?php

declare(strict_types=1);

namespace App\Services\Specialist;

use App\Models\Central\ExternalSpecialist;
use App\Models\Central\OwnerSpecialistMessage;
use App\Models\Central\OwnerSpecialistThread;
use App\Models\Central\User;
use App\Notifications\Specialist\NewOwnerSpecialistMessageNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Logika wątków Channel D (PR O5 epic 3) — właściciel konia ↔ external
 * specialist. Mirror SpecialistMessagingService, ale strony to owner
 * (central user) i specjalista.
 */
class OwnerSpecialistMessagingService
{
    /**
     * @param array<int,array<string,mixed>> $attachments
     */
    public function startThread(
        User $owner,
        ExternalSpecialist $specialist,
        string $subject,
        string $senderType,
        string $senderId,
        string $body,
        ?string $horseId = null,
        array $attachments = [],
    ): OwnerSpecialistThread {
        $thread = OwnerSpecialistThread::create([
            'owner_user_id' => $owner->id,
            'specialist_id' => $specialist->id,
            'horse_id' => $horseId,
            'subject' => $subject,
        ]);

        $this->reply($thread, $senderType, $senderId, $body, $attachments);

        return $thread;
    }

    /**
     * @param array<int,array<string,mixed>> $attachments
     */
    public function reply(
        OwnerSpecialistThread $thread,
        string $senderType,
        string $senderId,
        string $body,
        array $attachments = [],
    ): OwnerSpecialistMessage {
        $message = $thread->messages()->create([
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'body' => $body,
            'attachments' => $attachments === [] ? null : $attachments,
        ]);

        $thread->touchLastMessage($message->created_at);
        $this->notifyRecipient($thread, $message, $senderType);

        return $message;
    }

    public function markRead(OwnerSpecialistThread $thread, string $readerType): int
    {
        return OwnerSpecialistMessage::query()
            ->where('thread_id', $thread->id)
            ->unreadFor($readerType)
            ->update(['read_at' => now()]);
    }

    private function notifyRecipient(
        OwnerSpecialistThread $thread,
        OwnerSpecialistMessage $message,
        string $senderType,
    ): void {
        if ($senderType === OwnerSpecialistMessage::SENDER_SPECIALIST) {
            $senderName = $thread->specialist?->display_name ?? __('specialist/message.unknown_specialist');
            $recipient = $thread->owner;
        } else {
            $senderName = $thread->owner?->name ?? __('specialist/message.unknown_user');
            $recipient = $thread->specialist;
        }

        if ($recipient === null) {
            return;
        }

        Notification::send($recipient, new NewOwnerSpecialistMessageNotification($thread, $message, (string) $senderName));
    }
}
