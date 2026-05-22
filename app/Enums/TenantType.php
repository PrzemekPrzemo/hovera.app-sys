<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Discriminator dla tenant'a. Jeden tenant = jeden typ — osoba, która prowadzi
 * stajnię i firmę transportową, ma dwa osobne tenanty (multi-tenancy tego nie
 * komplikuje, istniejący tenant-switcher to natywnie obsługuje).
 *
 * Typy:
 *   - Stable      — pełen pakiet stable management (pensjonariusze, konie, vet,
 *                   kalendarz, faktury). Subscription paid plan.
 *   - Transporter — firma przewozowa: leady, oferty, faktury KSeF, fleet.
 *                   Subscription paid plan.
 *   - HorseOwner  — właściciel konia (bez stajni / firmy). Może składać
 *                   zamówienia na transport, widzi historię, ma swoje konie.
 *                   FREE forever — Hovera traktuje owner'ów jako consumer-side
 *                   marketplace participant, monetyzacja po stronie stable/transporter.
 *
 * Patrz docs/TRANSPORT.md §3.1.
 */
enum TenantType: string
{
    case Stable = 'stable';
    case Transporter = 'transporter';
    case HorseOwner = 'horse_owner';

    public function label(): string
    {
        return __('enums.tenant_type.'.$this->value);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }

    public function panelId(): string
    {
        return match ($this) {
            self::Stable => 'app',
            self::Transporter => 'transport',
            self::HorseOwner => 'owner',
        };
    }

    /**
     * Czy tenant tego typu jest free (no subscription required).
     * Wykorzystywane przez billing gates i provisioning.
     */
    public function isFreeTier(): bool
    {
        return $this === self::HorseOwner;
    }

    /**
     * Czy tenant tego typu wystawia faktury (VAT podatnik). Stable +
     * Transporter mogą wystawiać; HorseOwner jest konsumentem marketplace'u
     * — kupuje, ale sam FV nie wystawia.
     *
     * Używane do gating'u:
     *   - `KsefSettings` / `InvoicingSettings` / `PaymentSettings` w /app
     *   - widoczność navigation linków do tych pages
     *   - logiki billing'owej (faktury subskrypcji idą tylko do payers)
     */
    public function canIssueInvoices(): bool
    {
        return match ($this) {
            self::Stable, self::Transporter => true,
            self::HorseOwner => false,
        };
    }
}
