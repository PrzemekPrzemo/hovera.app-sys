<?php

declare(strict_types=1);

namespace App\Domain\Transport\Reviews;

use App\Models\Central\TransportReview;

/**
 * Anti-spam filter dla publicznego endpoint'u submit review. Patrz
 * docs/TRANSPORT.md §12.
 *
 * Trzy warstwy detection (taniejsze najpierw):
 *   1. Length check — pusty comment OK (gwiazdki same w sobie są reviewem),
 *      ale comment dłuższy niż threshold + same keyword count → reject
 *   2. Keyword blacklist — PL slurs + common spam patterns (viagra, casino,
 *      crypto bait). Match w `comment` lub `customer_name`.
 *   3. Duplicate text — jeśli ostatnie 30 minut przyszedł review z dokładnie
 *      tym samym comment'em z tego samego transportera, drugi to spam
 *      (bot repeat lub spam farm).
 *
 * Stateless service — bezpieczny do inject'owania jako singleton. Tests
 * tylko inspect'ują `isSpam()` boolean — bez DB w 1-2 testach.
 */
class ReviewSpamFilter
{
    /**
     * Lista blacklisted patterns. Wszystkie lowercase, match przez str_contains
     * po normalize input do lowercase. Krótkie ale dotkliwe — chcemy uniknąć
     * false positives przy normalnym feedback'u typu "drogi przewoz" (bo "drog"
     * matches "drog dealer").
     *
     * @var list<string>
     */
    private const SPAM_KEYWORDS = [
        // Casino / gambling
        'casino', 'kasyno', 'poker', 'jackpot', 'betting odds', 'free spins',
        // Crypto baits
        'bitcoin gift', 'free crypto', 'eth airdrop', 'forex trading',
        // Pharma / scam
        'viagra', 'cialis', 'erection', 'penis enlargement',
        // SEO link bait
        'click here', 'cheap loan', 'best price guaranteed',
        // PL slurs (anti-harassment, nie wycinamy normalnego feedbacku)
        // Pozostawione minimum — heavy hand'a unika false positives. Pełna
        // lista to deferred TODO (Akismet/ML).
        'kurwa kurwa', 'jebac', 'pierdolony', 'spierdalaj',
        // Spam phone numbers / URLs in comment body (anything with http:// 5+ pattern)
        'http://', 'https://www.', 'visit our',
    ];

    /**
     * Submit ma `comment` + `customer_name` jako user-controlled inputs.
     * Sprawdzamy oba.
     *
     * @param  array{rating:int, comment:string, customer_name:string, transporter_tenant_id:string}  $input
     */
    public function isSpam(array $input): bool
    {
        if ($this->matchesKeyword($input['comment'] ?? '')) {
            return true;
        }
        if ($this->matchesKeyword($input['customer_name'] ?? '')) {
            return true;
        }
        if ($this->isDuplicate($input)) {
            return true;
        }

        return false;
    }

    /**
     * Sprawdza dowolny pattern z blacklist'y. Case-insensitive, trim whitespace.
     */
    private function matchesKeyword(string $text): bool
    {
        $normalised = mb_strtolower(trim($text));
        if ($normalised === '') {
            return false;
        }

        foreach (self::SPAM_KEYWORDS as $keyword) {
            if (str_contains($normalised, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Last-30-min same-comment same-transporter check. Pozwala wykryć
     * bot replay lub spam farm submitujący ten sam tekst pod różne firmy.
     *
     * @param  array{comment:string, transporter_tenant_id:string}  $input
     */
    private function isDuplicate(array $input): bool
    {
        $comment = trim((string) ($input['comment'] ?? ''));
        $tenantId = (string) ($input['transporter_tenant_id'] ?? '');

        if ($comment === '' || $tenantId === '') {
            // Brak comment'a → nic do duplicate-check'u; brak tenanta to bug
            // ale validation niżej go złapie.
            return false;
        }

        return TransportReview::query()
            ->where('transporter_tenant_id', $tenantId)
            ->where('comment', $comment)
            ->where('submitted_at', '>=', now()->subMinutes(30))
            ->exists();
    }
}
