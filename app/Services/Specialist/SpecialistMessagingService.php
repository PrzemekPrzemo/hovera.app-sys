<?php

declare(strict_types=1);

namespace App\Services\Specialist;

use App\Models\Central\ExternalSpecialist;
use App\Models\Central\SpecialistMessage;
use App\Models\Central\SpecialistThread;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Notifications\Specialist\NewSpecialistMessageNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Logika wątków Channel B (PR O5 epic 1.4/1.5) — zakładanie wątków,
 * odpowiedzi, oznaczanie jako przeczytane oraz powiadamianie drugiej strony.
 *
 * Wątki żyją w central DB (cross-tenant), więc operujemy bez TenantManager
 * — standardowe Eloquent na connection `central`.
 */
class SpecialistMessagingService
{
    /**
     * Zakłada nowy wątek i zapisuje pierwszą wiadomość.
     *
     * @param array<int,array<string,mixed>> $attachments
     */
    public function startThread(
        Tenant $tenant,
        ExternalSpecialist $specialist,
        string $subject,
        string $senderType,
        string $senderId,
        string $body,
        ?string $horseId = null,
        array $attachments = [],
    ): SpecialistThread {
        $thread = SpecialistThread::create([
            'tenant_id' => $tenant->id,
            'specialist_id' => $specialist->id,
            'horse_id' => $horseId,
            'subject' => $subject,
        ]);

        $this->reply($thread, $senderType, $senderId, $body, $attachments);

        return $thread;
    }

    /**
     * Dodaje wiadomość do wątku, bumpuje last_message_at i powiadamia
     * drugą stronę.
     *
     * @param array<int,array<string,mixed>> $attachments
     */
    public function reply(
        SpecialistThread $thread,
        string $senderType,
        string $senderId,
        string $body,
        array $attachments = [],
    ): SpecialistMessage {
        $message = $thread->messages()->create([
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'body' => $body,
            'attachments' => $attachments === [] ? null : $attachments,
        ]);

        $thread->touchLastMessage($message->created_at);
        $this->notifyRecipients($thread, $message, $senderType, $senderId);

        return $message;
    }

    /**
     * Oznacza jako przeczytane wszystkie wiadomości w wątku napisane przez
     * przeciwną stronę względem czytającego.
     */
    public function markRead(SpecialistThread $thread, string $readerType): int
    {
        return SpecialistMessage::query()
            ->where('thread_id', $thread->id)
            ->unreadFor($readerType)
            ->update(['read_at' => now()]);
    }

    /**
     * Powiadamia stronę przeciwną do autora wiadomości.
     */
    private function notifyRecipients(
        SpecialistThread $thread,
        SpecialistMessage $message,
        string $senderType,
        string $senderId,
    ): void {
        if ($senderType === SpecialistMessage::SENDER_SPECIALIST) {
            // Specjalista → pracownicy stajni.
            $senderName = $thread->specialist?->display_name ?? __('specialist/message.unknown_specialist');
            $recipients = $this->tenantMembers($thread->tenant_id);
        } else {
            // Stajnia → specjalista.
            $senderName = User::query()->whereKey($senderId)->value('name') ?? __('specialist/message.unknown_user');
            $specialist = $thread->specialist;
            $recipients = $specialist !== null ? [$specialist] : [];
        }

        if ($recipients === []) {
            return;
        }

        Notification::send(
            $recipients,
            new NewSpecialistMessageNotification($thread, $message, (string) $senderName),
        );
    }

    /**
     * Aktywni członkowie stajni (central users) — odbiorcy notyfikacji.
     *
     * @return list<User>
     */
    private function tenantMembers(string $tenantId): array
    {
        $userIds = TenantMembership::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('revoked_at')
            ->pluck('user_id')
            ->all();

        return User::query()->whereIn('id', $userIds)->get()->all();
    }
}
