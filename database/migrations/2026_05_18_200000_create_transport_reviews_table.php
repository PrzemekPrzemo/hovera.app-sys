<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplace reviews — patrz docs/TRANSPORT.md §12 + §14.
 *
 * 1 invite per (lead × response) — anti-double. Invite generuje
 * TransportReviewInviteService 14 dni po preferred_date dla
 * zaakceptowanych ofert (status=accepted w transport_lead_responses).
 *
 * Hovera = pośrednik marketplace — opinie publikujemy raw (default
 * status=published po submit). Transporter może zgłosić do moderacji
 * (status=flagged), wtedy master admin decyduje publish/hide/reject.
 *
 * Email zamawiającego trzymamy w postaci hash + redacted (np. "j***@example.com")
 * — RODO data minimisation. Po wysyłce invite raw email nie jest potrzebny.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('transport_reviews', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('transporter_tenant_id')->index();
            $table->foreignUlid('lead_id')
                ->constrained('transport_leads')
                ->cascadeOnDelete();
            $table->foreignUlid('response_id')
                ->constrained('transport_lead_responses')
                ->cascadeOnDelete();
            $table->ulid('quote_id')->nullable();                       // best-effort backfill, quote w tenant DB

            // Magic link mechanism — token raw idzie do maila, w DB trzymamy
            // tylko sha256 hash (analog akceptacji oferty, ale z osobnym TTL
            // 30 dni — review ma duży window żeby klient zdążył ochłonąć/przemyśleć).
            // ->useCurrent() na obu — MySQL strict mode wymaga explicit default
            // dla TIMESTAMP NOT NULL. App code (TransportReviewInviteService)
            // i tak nadpisuje obie wartości przy create — default to tylko
            // sanity fallback gdyby ktoś insertował bez wypełnienia.
            $table->string('invite_token_hash', 64)->unique();
            $table->timestamp('invite_sent_at')->useCurrent();
            $table->timestamp('invite_expires_at')->useCurrent();

            // Content
            $table->unsignedTinyInteger('rating')->nullable();          // 1..5
            $table->text('comment')->nullable();
            $table->string('customer_name', 120)->nullable();            // snapshot z lead.originator_name
            $table->string('customer_email_hash', 64);                   // sha256(originator_email)
            $table->string('customer_email_redacted', 120)->nullable();  // "j***@example.com" do publicznego widoku

            // Lifecycle — invited → published (po submit) → flagged (zgłoszone)
            // lub hidden (zmoderowane). expired gdy minął invite_expires_at
            // bez submission (housekeeping cron może to ustawić, ale na razie
            // wystarczy że TTL token-side blokuje GET).
            $table->enum('status', ['invited', 'published', 'hidden', 'flagged', 'expired'])
                ->default('invited')
                ->index();

            // Transporter response — 1 publiczna odpowiedź. Edycja dozwolona
            // (idempotent), więc trzymamy tylko najnowszą + timestamp.
            $table->text('transporter_response')->nullable();
            $table->timestamp('transporter_responded_at')->nullable();

            // Moderacja
            $table->text('flagged_reason')->nullable();
            $table->timestamp('flagged_by_tenant_at')->nullable();
            $table->ulid('moderated_by_user_id')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->text('moderation_notes')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            // Anti-double — 1 invite per (lead, response). Idempotency dispatch'a.
            $table->unique(['lead_id', 'response_id']);
            // Szybki aggregate dla `/t/{slug}` — count/avg WHERE status=published.
            $table->index(['transporter_tenant_id', 'status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('transport_reviews');
    }
};
