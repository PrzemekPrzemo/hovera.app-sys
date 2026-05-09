<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Services\Tenancy\TenantRoleGate;

/**
 * Gates a Filament Resource / Page by the user's role in the currently
 * active tenant. Implementing classes provide `allowedRoles()` listing
 * which tenant roles can see the resource — master admins always pass.
 *
 * Usage:
 *   class FooResource extends Resource
 *   {
 *       use RestrictedByTenantRole;
 *
 *       protected static function allowedRoles(): array
 *       {
 *           return TenantRoleGate::FULL_ADMINS_AND_MANAGERS;
 *       }
 *   }
 */
trait RestrictedByTenantRole
{
    /**
     * @return list<string>
     */
    abstract protected static function allowedRoles(): array;

    public static function canAccess(): bool
    {
        return app(TenantRoleGate::class)->allows(static::allowedRoles());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canAccess();
    }
}
