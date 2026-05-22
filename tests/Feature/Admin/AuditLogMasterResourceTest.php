<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Admin\Resources\AuditLogMasterResource;
use App\Models\Central\AuditLogMaster;
use Tests\TestCase;

/**
 * Audit log MUSI byc immutable — kazdy edit/delete/create przez UI
 * to luka security (audyt traci wartosc gdy ktos moze go modyfikowac).
 *
 * Resource jest read-only przez override canCreate/canEdit/canDelete →
 * false. Sprawdzamy explicit.
 */
class AuditLogMasterResourceTest extends TestCase
{
    public function test_cannot_create_audit_log_entry(): void
    {
        $this->assertFalse(AuditLogMasterResource::canCreate());
    }

    public function test_cannot_edit_audit_log_entry(): void
    {
        $this->assertFalse(AuditLogMasterResource::canEdit(new AuditLogMaster));
    }

    public function test_cannot_delete_audit_log_entry(): void
    {
        $this->assertFalse(AuditLogMasterResource::canDelete(new AuditLogMaster));
    }

    public function test_resource_uses_audit_log_master_model(): void
    {
        $this->assertSame(AuditLogMaster::class, AuditLogMasterResource::getModel());
    }
}
