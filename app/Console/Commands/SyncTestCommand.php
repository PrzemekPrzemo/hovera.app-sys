<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Services\Sync\ChangeFeedService;
use App\Services\Sync\CursorCodec;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;

class SyncTestCommand extends Command
{
    protected $signature = 'sync:test {--tenant=demo} {--entities=horses,calendar_entries,client_messages} {--limit=50}';

    protected $description = 'Round-trip the change feed for a tenant; prints first batch.';

    public function handle(TenantManager $tenants, ChangeFeedService $feed): int
    {
        $slug = (string) $this->option('tenant');
        $tenant = Tenant::query()->where('slug', $slug)->orWhere('id', $slug)->first();
        if (! $tenant) {
            $this->error("Tenant '$slug' not found.");

            return self::FAILURE;
        }

        $tenants->setCurrent($tenant);
        $entities = array_filter(explode(',', (string) $this->option('entities')));
        $batch = $feed->pull(0, $entities, (int) $this->option('limit'));

        $this->info('Cursor: '.$batch['cursor']);
        [, $version] = CursorCodec::decode($batch['cursor']);
        $this->info('Decoded version: '.$version);
        $this->info('Changes: '.count($batch['changes']).($batch['has_more'] ? ' (more available)' : ''));
        foreach (array_slice($batch['changes'], 0, 10) as $change) {
            $this->line(sprintf(
                '  [%s] %s %s v=%d',
                $change['op'],
                $change['entity'],
                $change['id'],
                $change['sync_version']
            ));
        }

        return self::SUCCESS;
    }
}
