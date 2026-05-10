<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds sync_version + (where missing) deleted_at to every tenant table that
 * the mobile clients need to observe. We use Schema::hasColumn() guards so
 * this migration is safe to re-run on partial tenants.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'horses', 'horse_photos', 'horse_documents', 'horse_weight_measurements',
        'horse_feeding_plan_items', 'horse_messages',
        'calendar_entries', 'recurring_calendar_entries', 'calendar_entry_participants',
        'arenas', 'buildings', 'boxes', 'box_assignments',
        'clients', 'client_messages',
        'instructors', 'specialists',
        'passes', 'pass_uses',
        'invoices', 'invoice_items', 'payments',
        'health_records', 'treatment_templates',
        'boarding_services', 'stable_activities',
        'feed_items', 'feed_stock_movements',
    ];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            if (! Schema::hasTable($name)) {
                continue;
            }

            Schema::table($name, function (Blueprint $table) use ($name) {
                if (! Schema::hasColumn($name, 'sync_version')) {
                    $table->unsignedBigInteger('sync_version')->default(0)->index();
                }
                if (! Schema::hasColumn($name, 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            if (! Schema::hasTable($name)) {
                continue;
            }
            Schema::table($name, function (Blueprint $table) use ($name) {
                if (Schema::hasColumn($name, 'sync_version')) {
                    $table->dropColumn('sync_version');
                }
            });
        }
    }
};
