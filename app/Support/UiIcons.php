<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Single source of truth dla ikon Heroicon używanych w UI. Cel: spójność
 * (klient zawsze ten sam icon, koń zawsze ten sam itd.) bez duplikowania
 * literałów `'heroicon-o-...'` po 30+ miejscach.
 *
 * Convention: każdy entity ma jeden "primary" icon (variant `o` = outline,
 * Filament default). Niektóre entities mają wariant `solid` (`s`) używany
 * w tabelach jako BadgeColumn lub w UI gdy filled style pasuje lepiej —
 * w takim wypadku rozszerzamy o `SOLID_*` const.
 *
 * Patrz docs/UI-CONSISTENCY.md.
 */
final class UiIcons
{
    // Entities
    public const HORSE = 'heroicon-o-bolt';

    public const CLIENT = 'heroicon-o-user-group';

    public const STABLE = 'heroicon-o-building-storefront';

    public const TRANSPORTER = 'heroicon-o-truck';

    public const DRIVER = 'heroicon-o-identification';

    public const VEHICLE = 'heroicon-o-truck';

    public const QUOTE = 'heroicon-o-document-text';

    public const INVOICE = 'heroicon-o-document-text';

    public const PAYMENT = 'heroicon-o-credit-card';

    public const USER = 'heroicon-o-user';

    public const TEAM = 'heroicon-o-users';

    public const ROUTE = 'heroicon-o-map';

    public const CALENDAR = 'heroicon-o-calendar';

    public const SETTINGS = 'heroicon-o-cog-6-tooth';

    // Actions
    public const CREATE = 'heroicon-o-plus';

    public const EDIT = 'heroicon-o-pencil';

    public const DELETE = 'heroicon-o-trash';

    public const VIEW = 'heroicon-o-eye';

    public const DOWNLOAD = 'heroicon-o-arrow-down-tray';

    public const UPLOAD = 'heroicon-o-arrow-up-tray';

    public const SEARCH = 'heroicon-o-magnifying-glass';

    public const FILTER = 'heroicon-o-funnel';

    public const REFRESH = 'heroicon-o-arrow-path';

    // States / status
    public const SUCCESS = 'heroicon-o-check-circle';

    public const WARNING = 'heroicon-o-exclamation-triangle';

    public const ERROR = 'heroicon-o-x-circle';

    public const INFO = 'heroicon-o-information-circle';

    public const PENDING = 'heroicon-o-clock';

    public const VERIFIED = 'heroicon-o-shield-check';

    public const SPARKLES = 'heroicon-o-sparkles'; // onboarding / new feature

    // Communication
    public const MAIL = 'heroicon-o-envelope';

    public const PHONE = 'heroicon-o-phone';

    public const BELL = 'heroicon-o-bell';

    public const CHAT = 'heroicon-o-chat-bubble-left-right';
}
