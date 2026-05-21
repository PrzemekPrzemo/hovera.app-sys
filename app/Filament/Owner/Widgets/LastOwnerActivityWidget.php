<?php

declare(strict_types=1);

namespace App\Filament\Owner\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Owner Dashboard: lista ostatnich notifications dla zalogowanego usera.
 * Aggreguje:
 *   - owner.new_message (z PR 6.1 Faza 6)
 *   - owner.new_invoice (z PR 6.1 Faza 6)
 *   - owner.vet_visit_recorded (z PR 6.1 Faza 6)
 *
 * Pokazuje 5 najnowszych unread; klick → URL z notification.data['url'].
 * "Mark as read" przycisk per notification + "Mark all read" globalnie.
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 6 PR 6.2".
 */
class LastOwnerActivityWidget extends Widget
{
    protected static ?int $sort = -10;

    protected static string $view = 'filament.owner.widgets.last-owner-activity';

    /**
     * Pełna szerokość kontentu — Dashboard pokazuje to nad innymi
     * widget'ami statystycznymi.
     */
    protected int|string|array $columnSpan = 'full';

    /**
     * Pobierz 5 najnowszych unread notifications dla aktualnego user'a.
     * Filament wywoła to przy każdym renderze widget'u (lazy-eval'em).
     *
     * @return Collection<int, DatabaseNotification>
     */
    public function getNotifications(): Collection
    {
        $user = Auth::user();
        if ($user === null) {
            return collect();
        }

        return $user->unreadNotifications()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
    }

    public function getTotalUnreadCount(): int
    {
        $user = Auth::user();
        if ($user === null) {
            return 0;
        }

        return $user->unreadNotifications()->count();
    }

    /**
     * Klick na notification: markuje jako read + redirect na payload URL.
     * Filament Livewire action.
     */
    public function markRead(string $notificationId): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }
        $notification = $user->notifications()->where('id', $notificationId)->first();
        if ($notification === null) {
            return;
        }
        if ($notification->read_at === null) {
            $notification->markAsRead();
        }
    }

    public function markAllRead(): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }
        $user->unreadNotifications()->update(['read_at' => now()]);
    }

    /**
     * Helpery Blade — labels + ikony + colors per `kind` payload field.
     */
    public function labelFor(DatabaseNotification $notification): string
    {
        $kind = (string) ($notification->data['kind'] ?? '');

        return match ($kind) {
            'owner.new_message' => __('owner/dashboard.activity.label.new_message'),
            'owner.new_invoice' => __('owner/dashboard.activity.label.new_invoice'),
            'owner.vet_visit_recorded' => __('owner/dashboard.activity.label.vet_visit'),
            default => __('owner/dashboard.activity.label.fallback'),
        };
    }

    public function iconFor(DatabaseNotification $notification): string
    {
        $kind = (string) ($notification->data['kind'] ?? '');

        return match ($kind) {
            'owner.new_message' => 'heroicon-o-chat-bubble-left-right',
            'owner.new_invoice' => 'heroicon-o-document-text',
            'owner.vet_visit_recorded' => 'heroicon-o-heart',
            default => 'heroicon-o-bell',
        };
    }

    /**
     * @return array{badge_bg: string, icon_text: string}
     */
    public function classesFor(DatabaseNotification $notification): array
    {
        $kind = (string) ($notification->data['kind'] ?? '');

        return match ($kind) {
            'owner.new_message' => [
                'badge_bg' => 'bg-sky-100 dark:bg-sky-900/30',
                'icon_text' => 'text-sky-700 dark:text-sky-300',
            ],
            'owner.new_invoice' => [
                'badge_bg' => 'bg-emerald-100 dark:bg-emerald-900/30',
                'icon_text' => 'text-emerald-700 dark:text-emerald-300',
            ],
            'owner.vet_visit_recorded' => [
                'badge_bg' => 'bg-rose-100 dark:bg-rose-900/30',
                'icon_text' => 'text-rose-700 dark:text-rose-300',
            ],
            default => [
                'badge_bg' => 'bg-gray-100 dark:bg-gray-800',
                'icon_text' => 'text-gray-700 dark:text-gray-300',
            ],
        };
    }

    /**
     * Wyciąga 1-linijkową summary z notification.data per kind.
     */
    public function summaryFor(DatabaseNotification $notification): string
    {
        $data = is_array($notification->data) ? $notification->data : [];
        $kind = (string) ($data['kind'] ?? '');

        return match ($kind) {
            'owner.new_message' => sprintf(
                '%s — %s%s',
                (string) ($data['stable_name'] ?? ''),
                (string) ($data['horse_name'] ?? ''),
                ! empty($data['subject']) ? ' · '.$data['subject'] : '',
            ),
            'owner.new_invoice' => sprintf(
                '%s%s — %s',
                (string) ($data['stable_name'] ?? ''),
                ! empty($data['invoice_number']) ? ' '.$data['invoice_number'] : '',
                $this->formatCents((int) ($data['total_cents'] ?? 0), (string) ($data['currency'] ?? 'PLN')),
            ),
            'owner.vet_visit_recorded' => sprintf(
                '%s — %s · %s',
                (string) ($data['stable_name'] ?? ''),
                (string) ($data['horse_name'] ?? ''),
                __('enums.health_record_type.'.($data['record_type'] ?? 'other')),
            ),
            default => '—',
        };
    }

    public function urlFor(DatabaseNotification $notification): ?string
    {
        $url = $notification->data['url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function relativeTime(DatabaseNotification $notification): string
    {
        $createdAt = $notification->created_at;
        if (! $createdAt instanceof Carbon) {
            return '';
        }

        return $createdAt->diffForHumans();
    }

    private function formatCents(int $cents, string $currency): string
    {
        return number_format($cents / 100, 2, ',', ' ').' '.$currency;
    }
}
