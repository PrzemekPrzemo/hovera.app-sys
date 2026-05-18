<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status weryfikacji konta transportera. Patrz docs/TRANSPORT.md
 * (sekcja dorobiona po feedbacku produkcyjnym).
 *
 *   pending       → świeży signup, transporter ma jeszcze coś dograć
 *   under_review  → wszystkie dokumenty wgrane, master admin sprawdza
 *   verified      → master admin zatwierdził, konto aktywne (FV/oferty/marketplace)
 *   rejected      → master admin odrzucił z powodem; transporter może
 *                   poprawić i ponownie wgrać dokumenty → wraca na pending
 *
 * Stable tenant'y i.e. type=stable mają to pole NULL (irrelevant).
 */
enum VerificationStatus: string
{
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('enums.verification_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::UnderReview => 'info',
            self::Verified => 'success',
            self::Rejected => 'danger',
        };
    }

    public function isVerified(): bool
    {
        return $this === self::Verified;
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }
}
