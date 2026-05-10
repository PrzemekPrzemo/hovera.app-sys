<?php

declare(strict_types=1);

namespace App\Services\Sync\Handlers;

use App\Models\Central\TenantMembership;

interface MutationHandler
{
    public function handle(string $entity, string $op, array $mutation, TenantMembership $membership): MutationResult;
}
