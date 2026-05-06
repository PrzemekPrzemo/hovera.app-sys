<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Models\Central\Tenant;
use App\Tenancy\Provisioner;
use Illuminate\Support\Facades\DB;

/**
 * Two-step delete:
 *   - softDelete()  → status='deleted', kept for grace period
 *   - destroy()     → drops MySQL DB + user, then forceDelete()
 *
 * Filament UI exposes both as separate actions.
 */
class DeleteTenant
{
    public function __construct(private readonly Provisioner $provisioner) {}

    public function softDelete(Tenant $tenant): void
    {
        DB::connection('central')->transaction(function () use ($tenant) {
            $tenant->forceFill(['status' => 'deleted', 'suspended_at' => now()])->save();
            $tenant->delete();
        });
    }

    public function destroy(Tenant $tenant): void
    {
        $this->provisioner->destroy($tenant);
        $tenant->forceDelete();
    }
}
