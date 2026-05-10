<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Tenant\SyncVersionCounter;

/**
 * Mix in to any TenantModel that mobile clients sync against.
 *
 * On every save/delete the model's `sync_version` column is bumped to the
 * tenant's monotonic counter, so the mobile change-feed can return only
 * rows whose version > {client cursor}.
 *
 * Soft deletes also bump sync_version so that the mobile client can
 * observe the tombstone via the same feed.
 */
trait HasSyncVersion
{
    public static function bootHasSyncVersion(): void
    {
        static::saving(function ($model): void {
            $model->sync_version = SyncVersionCounter::next();
        });

        static::deleting(function ($model): void {
            // For both soft and hard deletes, stamp + persist the new version
            // so the row leaves the change feed with a fresh tombstone.
            $model->sync_version = SyncVersionCounter::next();
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                $model->saveQuietly();
            }
        });
    }
}
