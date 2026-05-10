<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SyncVersionCounter extends Model
{
    protected $connection = 'tenant';
    protected $table = 'sync_version_counters';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;

    /**
     * Atomic monotonic next() against the per-tenant counter row. Returns
     * the new value. We rely on InnoDB row-level locking via UPDATE.
     */
    public static function next(): int
    {
        return DB::connection('tenant')->transaction(function () {
            DB::connection('tenant')
                ->table('sync_version_counters')
                ->where('id', 1)
                ->update([
                    'current_version' => DB::raw('current_version + 1'),
                    'updated_at' => now(),
                ]);

            return (int) DB::connection('tenant')
                ->table('sync_version_counters')
                ->where('id', 1)
                ->value('current_version');
        });
    }

    public static function current(): int
    {
        return (int) DB::connection('tenant')
            ->table('sync_version_counters')
            ->where('id', 1)
            ->value('current_version') ?? 0;
    }
}
