<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use Illuminate\Console\Command;

class TenantsListCommand extends Command
{
    protected $signature = 'tenants:list {--status=}';
    protected $description = 'List tenants registered in the central database.';

    public function handle(): int
    {
        $query = Tenant::query()->orderBy('created_at');

        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }

        $rows = $query->get()->map(fn (Tenant $t) => [
            'slug'       => $t->slug,
            'name'       => $t->name,
            'status'     => $t->status,
            'plan'       => $t->plan?->code ?? '—',
            'db_name'    => $t->db_name,
            'created_at' => $t->created_at?->toDateTimeString() ?? '—',
        ])->all();

        if ($rows === []) {
            $this->info('No tenants found.');
            return self::SUCCESS;
        }

        $this->table(['slug', 'name', 'status', 'plan', 'db_name', 'created_at'], $rows);
        return self::SUCCESS;
    }
}
