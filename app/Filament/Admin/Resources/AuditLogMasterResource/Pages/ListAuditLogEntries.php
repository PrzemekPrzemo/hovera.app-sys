<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AuditLogMasterResource\Pages;

use App\Filament\Admin\Resources\AuditLogMasterResource;
use Filament\Resources\Pages\ListRecords;

class ListAuditLogEntries extends ListRecords
{
    protected static string $resource = AuditLogMasterResource::class;
}
