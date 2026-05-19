<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add-on purchases — patrz docs/TRANSPORT.md §13 (master admin) +
 * docs/HOVERA.md (one-time charges via P24).
 *
 * Każdy kupiony add-on (migrate_excel, invoice_setup, onboarding_live,
 * migrate_system + recurring extra_driver / extra_vehicle) zostawia ślad
 * jako wiersz w tej tabeli. Master admin tworzy purchase z poziomu
 * panelu /admin, system generuje link P24 (Hovera jako merchant of
 * record), klient (tenant) płaci → webhook → status=paid + side-effect
 * (np. rozszerzenie limitu `max_drivers` dla extra_driver).
 *
 * Tabela CENTRAL (jak invoices) — żyje per-Hovera, nie per-tenant.
 *
 * Status enum (string żeby było stabilne wzgl. rotacji migracji):
 *   - pending: link wygenerowany, oczekujemy płatności
 *   - paid: webhook potwierdził sukces (idempotent)
 *   - failed: P24 zwrócił rejected / mismatch
 *   - cancelled: master admin cofnął
 */
return new class extends Migration
{
    public function getConnection(): string
    {
        return 'central';
    }

    public function up(): void
    {
        Schema::connection('central')->create('addon_purchases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('plan_addon_id')->constrained('plan_addons');

            // Snapshot wartości w momencie zakupu — żeby zmiana cennika nie
            // przepisała historii. Mirror konwencji z invoices.snapshot.
            $table->string('addon_code', 64);
            $table->string('addon_name', 200);
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_cents');

            // Status + audit
            $table->string('status', 16)->default('pending')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            // P24 transaction tracking (Hovera jako merchant — central P24
            // creds z services.przelewy24.*, NIE z tenants.settings).
            $table->string('p24_session_id', 100)->nullable()->unique();
            $table->text('p24_payment_url')->nullable();
            $table->string('p24_order_id', 32)->nullable();
            $table->timestamp('p24_paid_at')->nullable();

            // Side-effect tracking — np. {"extended_limit": "max_drivers", "delta": 1}
            $table->json('side_effect_metadata')->nullable();
            $table->timestamp('side_effect_applied_at')->nullable();

            $table->ulid('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('addon_code');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('addon_purchases');
    }
};
