<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\CalendarEntryStatus;
use App\Enums\CalendarEntryType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalendarEntry extends TenantModel
{
    use SoftDeletes;

    protected $table = 'calendar_entries';

    protected $fillable = [
        'type', 'starts_at', 'ends_at',
        'horse_id', 'instructor_id', 'arena_id', 'client_id',
        'recurrence_id', 'recurrence_occurrence',
        'status', 'title', 'notes', 'price_cents',
        'metadata', 'created_by_central_user_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
            'price_cents' => 'integer',
            'type' => CalendarEntryType::class,
            'status' => CalendarEntryStatus::class,
        ];
    }

    public function horse(): BelongsTo
    {
        return $this->belongsTo(Horse::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function arena(): BelongsTo
    {
        return $this->belongsTo(Arena::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function recurrence(): BelongsTo
    {
        return $this->belongsTo(RecurringCalendarEntry::class, 'recurrence_id');
    }

    /**
     * Entries whose [starts_at, ends_at) overlaps the given window.
     * Two intervals [a,b) and [c,d) overlap iff a < d AND c < b.
     */
    public function scopeOverlapping(Builder $query, \DateTimeInterface $startsAt, \DateTimeInterface $endsAt): Builder
    {
        return $query
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);
    }

    /**
     * Only entries whose status actually occupies a resource slot.
     */
    public function scopeBlockingResources(Builder $query): Builder
    {
        return $query->whereIn('status', collect(CalendarEntryStatus::cases())
            ->filter(fn (CalendarEntryStatus $s) => $s->blocksResources())
            ->map(fn (CalendarEntryStatus $s) => $s->value)
            ->all());
    }

    public function durationMinutes(): int
    {
        return (int) $this->starts_at->diffInMinutes($this->ends_at);
    }
}
