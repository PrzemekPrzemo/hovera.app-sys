<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Base for everything that lives in the per-tenant database.
 * The `tenant` connection is reconfigured at runtime by TenantManager
 * — these models always read from whichever stable's DB is active.
 */
abstract class TenantModel extends Model
{
    use HasUlids;

    protected $connection = 'tenant';
}
