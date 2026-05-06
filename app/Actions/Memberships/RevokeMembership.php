<?php

declare(strict_types=1);

namespace App\Actions\Memberships;

use App\Models\Central\TenantMembership;

class RevokeMembership
{
    public function execute(TenantMembership $membership): void
    {
        if ($membership->revoked_at !== null) {
            return;
        }

        $membership->forceFill(['revoked_at' => now()])->save();
    }

    public function reactivate(TenantMembership $membership): void
    {
        if ($membership->revoked_at === null) {
            return;
        }

        $membership->forceFill(['revoked_at' => null])->save();
    }
}
