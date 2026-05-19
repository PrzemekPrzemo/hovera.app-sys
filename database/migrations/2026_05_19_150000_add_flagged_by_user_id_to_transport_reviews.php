<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit who z tenant'a flagował review. Wcześniej mieliśmy tylko
 * `flagged_by_tenant_at` timestamp, ale przy tenants z wieloma user'ami
 * (owner/admin/manager) trzeba wiedzieć który user kliknął flag —
 * dla audit log + przyszłej obrony przed flag-abuse'em.
 *
 * Patrz docs/TRANSPORT.md §12 (reviews moderation).
 */
return new class extends Migration
{
    public function getConnection(): string
    {
        return 'central';
    }

    public function up(): void
    {
        // Idempotent guards — produkcja może mieć kolumnę dodaną ręcznie
        // (manual ALTER albo wcześniejsza próba migracji). Bez guard'ów
        // re-run deploy padał na "Duplicate column".
        Schema::connection('central')->table('transport_reviews', function (Blueprint $table) {
            if (! Schema::connection('central')->hasColumn('transport_reviews', 'flagged_by_user_id')) {
                $table->ulid('flagged_by_user_id')->nullable()->after('flagged_by_tenant_at');
            }
        });

        // Explicit short index name — auto-generated
        // `transport_reviews_transporter_tenant_id_flagged_by_tenant_at_index`
        // ma 67 znaków, MySQL limit = 64. Bez `name` argumentu deploy padał
        // na MySQL prod (handover §6 gotcha #2). Patrz docs/TRANSPORT.md.
        if (! $this->hasIndex('transport_reviews', 'tr_tenant_flagged_idx')) {
            Schema::connection('central')->table('transport_reviews', function (Blueprint $table) {
                $table->index(['transporter_tenant_id', 'flagged_by_tenant_at'], 'tr_tenant_flagged_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('central')->table('transport_reviews', function (Blueprint $table) {
            if ($this->hasIndex('transport_reviews', 'tr_tenant_flagged_idx')) {
                $table->dropIndex('tr_tenant_flagged_idx');
            }
            if (Schema::connection('central')->hasColumn('transport_reviews', 'flagged_by_user_id')) {
                $table->dropColumn('flagged_by_user_id');
            }
        });
    }

    /**
     * Cross-driver index existence check. MySQL → information_schema,
     * SQLite (testy) → pragma_index_list. Defensive — żaden mechanizm
     * Laravel'a nie ma stabilnego API dla regular index check przez wszystkie
     * drivery.
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        $conn = Schema::connection('central')->getConnection();
        $driver = $conn->getDriverName();

        if ($driver === 'sqlite') {
            $rows = $conn->select("PRAGMA index_list({$table})");
            foreach ($rows as $r) {
                if (($r->name ?? '') === $indexName) {
                    return true;
                }
            }

            return false;
        }

        // MySQL / MariaDB
        $rows = $conn->select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $indexName],
        );

        return $rows !== [];
    }
};
