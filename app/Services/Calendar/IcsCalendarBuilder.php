<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Enums\CalendarEntryStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Instructor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Generates an RFC 5545 (iCalendar) feed for an instructor's lessons.
 * Output is consumed by Google Calendar / Outlook / Apple Calendar
 * via "Add calendar by URL" — they re-fetch every few hours.
 *
 * Range window: 6 months back, 12 months forward. Past entries are
 * useful for at-a-glance recap; cutting off avoids dragging years
 * of history through every poll.
 */
class IcsCalendarBuilder
{
    private const PAST_DAYS = 180;

    private const FUTURE_DAYS = 365;

    public function build(Tenant $tenant, Instructor $instructor, Collection $entries): string
    {
        $tenantName = $this->escape($tenant->name);
        $instructorName = $this->escape($instructor->name);
        $calName = "hovera · {$instructorName} ({$tenantName})";

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//hovera//instructor calendar//PL',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->fold($calName),
            'X-WR-TIMEZONE:'.$this->escape((string) ($tenant->timezone ?? config('app.timezone'))),
        ];

        foreach ($entries as $entry) {
            $lines = array_merge($lines, $this->event($tenant, $entry));
        }

        $lines[] = 'END:VCALENDAR';

        // RFC 5545 mandates CRLF line endings.
        return implode("\r\n", $lines)."\r\n";
    }

    public function windowStart(): Carbon
    {
        return now()->subDays(self::PAST_DAYS);
    }

    public function windowEnd(): Carbon
    {
        return now()->addDays(self::FUTURE_DAYS);
    }

    /**
     * @return array<int, string>
     */
    private function event(Tenant $tenant, $entry): array
    {
        $uid = sprintf('%s-entry-%s@hovera.app', $tenant->slug, $entry->id);
        $status = $this->statusForVevent($entry->status);
        $summary = $this->summaryFor($entry);
        $description = $this->descriptionFor($entry);

        return [
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'DTSTAMP:'.$this->formatUtc($entry->updated_at ?? $entry->created_at ?? now()),
            'DTSTART:'.$this->formatUtc($entry->starts_at),
            'DTEND:'.$this->formatUtc($entry->ends_at),
            'STATUS:'.$status,
            'SUMMARY:'.$this->fold('SUMMARY:'.$this->escape($summary)),
            $description !== '' ? 'DESCRIPTION:'.$this->fold('DESCRIPTION:'.$this->escape($description)) : null,
            'TRANSP:'.($status === 'CANCELLED' ? 'TRANSPARENT' : 'OPAQUE'),
            'END:VEVENT',
        ];
    }

    private function summaryFor($entry): string
    {
        $bits = [];
        if ($entry->title) {
            $bits[] = (string) $entry->title;
        } elseif (method_exists($entry->type, 'label')) {
            $bits[] = (string) $entry->type->label();
        }

        if ($entry->horse?->name) {
            $bits[] = '🐴 '.$entry->horse->name;
        }
        if ($entry->arena?->name) {
            $bits[] = '📍 '.$entry->arena->name;
        }

        return implode(' · ', array_filter($bits));
    }

    private function descriptionFor($entry): string
    {
        $bits = [];
        if ($entry->client?->name) {
            $bits[] = 'Klient: '.$entry->client->name;
        }
        if ($entry->notes) {
            $bits[] = (string) $entry->notes;
        }

        return implode("\n", $bits);
    }

    private function statusForVevent(CalendarEntryStatus $status): string
    {
        return match ($status) {
            CalendarEntryStatus::Cancelled,
            CalendarEntryStatus::NoShow => 'CANCELLED',
            CalendarEntryStatus::Confirmed,
            CalendarEntryStatus::Completed => 'CONFIRMED',
            default => 'TENTATIVE',
        };
    }

    private function formatUtc($value): string
    {
        return Carbon::parse($value)->utc()->format('Ymd\THis\Z');
    }

    /**
     * RFC 5545 §3.3.11: escape `\`, `,`, `;` and newlines.
     */
    private function escape(string $value): string
    {
        $value = str_replace(['\\', "\r\n", "\n", "\r", ',', ';'],
            ['\\\\', '\\n', '\\n', '\\n', '\\,', '\\;'], $value);

        return $value;
    }

    /**
     * RFC 5545 §3.1: lines longer than 75 octets must be folded by
     * inserting CRLF + single space. Caller passes the line including
     * its property name so the limit is computed correctly.
     */
    private function fold(string $line): string
    {
        $clean = ltrim($line);
        // Strip the prefix used only for length calculation.
        $colon = strpos($clean, ':');
        $value = $colon !== false ? substr($clean, $colon + 1) : $clean;

        if (strlen($clean) <= 75) {
            return $value;
        }

        $chunks = [];
        $remaining = $value;
        $first = true;
        while (strlen($remaining) > 0) {
            $limit = $first ? (75 - ($colon !== false ? $colon + 1 : 0)) : 74;
            $chunks[] = substr($remaining, 0, $limit);
            $remaining = substr($remaining, $limit);
            $first = false;
        }

        return implode("\r\n ", $chunks);
    }
}
