<?php

declare(strict_types=1);

namespace App\Actions\Stable;

use App\Models\Central\Tenant;
use App\Models\Tenant\Horse;
use App\Models\Tenant\HorseMessage;
use App\Notifications\HorseMessageNotification;
use App\Services\Portal\ClientMessageJournal;
use App\Services\TenantAuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification as MailFacade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Wyślij wiadomość: stajnia → klient lub klient → stajnia.
 *
 *   - Persistuje wpis w `horse_messages`
 *   - Zapisuje załączniki na dysku `local` w katalogu
 *     `storage/app/horse-messages/{tenant_id}/{horse_id}/{ulid}_{name}`
 *   - Wysyła powiadomienie e-mail (zawsze do drugiej strony)
 *   - Pisze do ClientMessageJournal gdy adresat to klient (pojawi się
 *     w portalu w sekcji "Wiadomości")
 *
 * Załączniki: max 5 plików / wiadomość, max 10 MB każdy. Whitelist
 * MIME types: jpeg/png/webp/heic/pdf/doc/docx (whitelisting tu, nie w
 * Filament UI bo dotyczy też portalu klienta).
 */
class SendHorseMessage
{
    public const MAX_ATTACHMENTS = 5;

    public const MAX_SIZE_BYTES = 10 * 1024 * 1024;   // 10 MB

    public const ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/webp', 'image/heic',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
    ];

    public function __construct(
        private readonly TenantAuditLogger $audit,
        private readonly ClientMessageJournal $journal,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $attachments
     */
    public function fromStable(
        Tenant $tenant,
        Horse $horse,
        string $body,
        ?string $subject = null,
        ?string $senderUserId = null,
        array $attachments = [],
    ): HorseMessage {
        if (! $horse->owner_client_id) {
            throw ValidationException::withMessages([
                'horse' => 'Ten koń nie ma przypisanego właściciela — nie ma do kogo wysłać wiadomości.',
            ]);
        }

        $stored = $this->storeAttachments($tenant, $horse, $attachments);

        $message = HorseMessage::create([
            'id' => (string) Str::ulid(),
            'horse_id' => $horse->id,
            'direction' => 'from_stable',
            'sender_user_id' => $senderUserId,
            'client_id' => $horse->owner_client_id,
            'subject' => $subject,
            'body' => $body,
            'attachments' => $stored,
            'sent_at' => now(),
        ]);

        $client = $horse->owner;
        if ($client?->email) {
            MailFacade::route('mail', $client->email)->notify(new HorseMessageNotification(
                tenantName: $tenant->name,
                horseName: $horse->name,
                portalUrl: route('client_portal.horses.show', ['slug' => $tenant->slug, 'horse' => $horse->id]),
                fromLabel: $tenant->name,
                subject: $subject,
                bodyPreview: $this->truncate($body, 280),
                attachmentCount: $message->attachmentCount(),
            ));
            $this->journal->record(
                $client,
                'horse.message',
                "Nowa wiadomość ze stajni — {$horse->name}",
                ['attachments' => $message->attachmentCount()],
                'HorseMessage',
                (string) $message->id,
            );
        }

        $this->audit->record('horse_message.sent', 'HorseMessage', (string) $message->id, [
            'direction' => 'from_stable',
            'horse_id' => $horse->id,
        ]);

        return $message;
    }

    /**
     * @param  array<int, UploadedFile>  $attachments
     */
    public function fromClient(
        Tenant $tenant,
        Horse $horse,
        string $clientId,
        string $body,
        ?string $subject = null,
        array $attachments = [],
    ): HorseMessage {
        if ($horse->owner_client_id !== $clientId) {
            throw ValidationException::withMessages([
                'horse' => 'Brak uprawnień — ten koń nie należy do Ciebie.',
            ]);
        }

        $stored = $this->storeAttachments($tenant, $horse, $attachments);

        $message = HorseMessage::create([
            'id' => (string) Str::ulid(),
            'horse_id' => $horse->id,
            'direction' => 'from_client',
            'client_id' => $clientId,
            'subject' => $subject,
            'body' => $body,
            'attachments' => $stored,
            'sent_at' => now(),
        ]);

        // Powiadom wszystkich owner/admin stajni
        $ownerEmails = $tenant->memberships()
            ->whereIn('role', ['owner', 'admin'])
            ->whereNull('revoked_at')
            ->with('user:id,email')
            ->get()
            ->pluck('user.email')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($ownerEmails !== []) {
            MailFacade::route('mail', $ownerEmails)->notify(new HorseMessageNotification(
                tenantName: $tenant->name,
                horseName: $horse->name,
                portalUrl: route('filament.app.resources.horses.edit', ['record' => $horse->id]),
                fromLabel: (string) ($horse->owner?->name ?? 'Właściciel konia'),
                subject: $subject,
                bodyPreview: $this->truncate($body, 280),
                attachmentCount: $message->attachmentCount(),
            ));
        }

        $this->audit->record('horse_message.sent', 'HorseMessage', (string) $message->id, [
            'direction' => 'from_client',
            'horse_id' => $horse->id,
        ]);

        return $message;
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, array{path:string, original_name:string, mime:string, size:int}>
     */
    private function storeAttachments(Tenant $tenant, Horse $horse, array $files): array
    {
        if ($files === []) {
            return [];
        }
        if (count($files) > self::MAX_ATTACHMENTS) {
            throw ValidationException::withMessages([
                'attachments' => 'Max '.self::MAX_ATTACHMENTS.' załączników na wiadomość.',
            ]);
        }

        $stored = [];
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            if ($file->getSize() > self::MAX_SIZE_BYTES) {
                throw ValidationException::withMessages([
                    'attachments' => 'Załącznik "'.$file->getClientOriginalName().'" przekracza 10 MB.',
                ]);
            }
            $mime = (string) $file->getMimeType();
            if (! in_array($mime, self::ALLOWED_MIMES, true)) {
                throw ValidationException::withMessages([
                    'attachments' => 'Niewspierany typ pliku: '.$mime.' ("'.$file->getClientOriginalName().'").',
                ]);
            }

            $dir = "horse-messages/{$tenant->id}/{$horse->id}";
            $name = (string) Str::ulid().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
            $path = $file->storeAs($dir, $name, 'local');

            $stored[] = [
                'path' => (string) $path,
                'original_name' => (string) $file->getClientOriginalName(),
                'mime' => $mime,
                'size' => (int) $file->getSize(),
            ];
        }

        return $stored;
    }

    private function truncate(string $text, int $limit): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        return mb_strlen($text) <= $limit ? $text : mb_substr($text, 0, $limit - 1).'…';
    }
}
