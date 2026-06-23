<?php

declare(strict_types=1);

namespace App\Jobs\Internal;

use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Tenant\InternalChannel;
use App\Models\Tenant\InternalChannelMember;
use App\Models\Tenant\InternalMessage;
use App\Notifications\Internal\InternalDailyDigestNotification;
use App\Tenancy\TenantManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Channel C (PR O5 epic 2) — dzienny digest nieprzeczytanych wiadomości
 * z kanałów wewnętrznych. Scheduler: codziennie 08:00 Europe/Warsaw.
 *
 * Iteruje wszystkie stajnie, switch'uje na ich DB (TenantManager::execute),
 * agreguje per-member unread z ostatnich 24h (i po `last_read_at`) grupując
 * po kanale, po czym wysyła notyfikację do central usera. Userów z 0 unread
 * pomijamy (per captured decisions §4 — skip empty).
 */
class SendDailyDigestJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function uniqueId(): string
    {
        return 'internal-digest:'.now()->toDateString();
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(TenantManager $tenants): void
    {
        $cutoff = now()->subDay();
        $sent = 0;

        Tenant::query()->stables()->orderBy('id')->chunk(50, function ($stables) use ($tenants, $cutoff, &$sent) {
            foreach ($stables as $stable) {
                try {
                    $sent += $this->digestForTenant($tenants, $stable, $cutoff);
                } catch (Throwable $e) {
                    Log::warning('internal-digest: tenant failed', [
                        'tenant_id' => $stable->id,
                        'error' => $e->getMessage(),
                    ]);
                    report($e);
                }
            }
        });

        Log::info('internal-digest: done', ['notifications_sent' => $sent]);
    }

    /**
     * Buduje i wysyła digesty dla jednej stajni. Zwraca liczbę wysłanych
     * notyfikacji.
     */
    private function digestForTenant(TenantManager $tenants, Tenant $stable, Carbon $cutoff): int
    {
        // Zbieramy payloady w kontekście tenanta, wysyłamy po wyjściu (mail +
        // database notification piszą do central usera).
        $payloads = $tenants->execute($stable, function () use ($cutoff): array {
            if (! Schema::connection('tenant')->hasTable('internal_channels')) {
                return [];
            }

            $channels = InternalChannel::query()->get(['id', 'name'])->keyBy('id');
            if ($channels->isEmpty()) {
                return [];
            }

            $payloads = [];

            $members = InternalChannelMember::query()
                ->where('notifications_enabled', true)
                ->get();

            foreach ($members->groupBy('user_id') as $userId => $memberships) {
                $groups = [];
                $total = 0;

                foreach ($memberships as $member) {
                    $channel = $channels->get($member->channel_id);
                    if ($channel === null) {
                        continue;
                    }

                    $since = $member->last_read_at !== null && $member->last_read_at->gt($cutoff)
                        ? $member->last_read_at
                        : $cutoff;

                    $count = InternalMessage::query()
                        ->where('channel_id', $member->channel_id)
                        ->where('author_user_id', '!=', $userId)
                        ->where('created_at', '>=', $since)
                        ->count();

                    if ($count > 0) {
                        $groups[] = ['name' => $channel->name, 'count' => $count];
                        $total += $count;
                    }
                }

                if ($total > 0) {
                    $payloads[(string) $userId] = ['groups' => $groups, 'total' => $total];
                }
            }

            return $payloads;
        });

        $sent = 0;
        foreach ($payloads as $userId => $payload) {
            $user = User::query()->find($userId);
            if ($user === null) {
                continue;
            }

            Notification::send(
                $user,
                new InternalDailyDigestNotification($stable, $payload['groups'], $payload['total']),
            );
            $sent++;
        }

        return $sent;
    }
}
