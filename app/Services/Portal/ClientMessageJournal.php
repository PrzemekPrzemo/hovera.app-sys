<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Models\Tenant\Client;
use App\Models\Tenant\ClientMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Single entry-point used by every dispatch site that mails the client.
 *
 * Why a journal and not Laravel's database notifications channel?
 *  - Notifications would store every recipient (incl. owners) in one
 *    table; we want a per-client log scoped to the tenant DB.
 *  - The journal stays decoupled from the actual mail driver; failure
 *    to record never blocks the email from going out.
 *
 * Every record() call is wrapped in a try/swallow because journaling
 * is an audit nicety — if it fails we log to error and move on. Never
 * let a portal write break a transactional email.
 */
class ClientMessageJournal
{
    public function record(
        Client $client,
        string $type,
        string $subject,
        ?array $preview = null,
        ?string $relatedType = null,
        ?string $relatedId = null,
    ): ?ClientMessage {
        if (! $client->email) {
            return null;
        }

        try {
            return ClientMessage::create([
                'id' => (string) Str::ulid(),
                'client_id' => $client->id,
                'type' => $type,
                'subject' => $subject,
                'to_email' => $client->email,
                'preview' => $preview,
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'sent_at' => Carbon::now(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
