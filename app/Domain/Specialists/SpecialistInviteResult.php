<?php

declare(strict_types=1);

namespace App\Domain\Specialists;

use App\Models\Central\ExternalSpecialist;
use App\Models\Central\SpecialistMagicLink;

/**
 * Wynik `SpecialistInviteService::invite()`. Immutable DTO informujący
 * caller'a jaka akcja została wykonana (UI Notification różnicuje).
 */
final class SpecialistInviteResult
{
    public const STATUS_CREATED = 'created';

    public const STATUS_REISSUED = 'reissued';

    public const STATUS_EXISTING_ALREADY_SETUP = 'existing_already_setup';

    private function __construct(
        public readonly string $status,
        public readonly ExternalSpecialist $specialist,
        public readonly ?SpecialistMagicLink $magicLink,
    ) {}

    public static function created(ExternalSpecialist $specialist, SpecialistMagicLink $link): self
    {
        return new self(self::STATUS_CREATED, $specialist, $link);
    }

    public static function reissued(ExternalSpecialist $specialist, SpecialistMagicLink $link): self
    {
        return new self(self::STATUS_REISSUED, $specialist, $link);
    }

    public static function existingAlreadySetup(ExternalSpecialist $specialist): self
    {
        return new self(self::STATUS_EXISTING_ALREADY_SETUP, $specialist, null);
    }

    public function isNewInvite(): bool
    {
        return $this->status === self::STATUS_CREATED;
    }

    public function isReissue(): bool
    {
        return $this->status === self::STATUS_REISSUED;
    }

    public function isExistingAlreadySetup(): bool
    {
        return $this->status === self::STATUS_EXISTING_ALREADY_SETUP;
    }
}
