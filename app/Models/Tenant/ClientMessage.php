<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientMessage extends TenantModel
{
    protected $table = 'client_messages';

    protected $fillable = [
        'client_id',
        'type',
        'subject',
        'to_email',
        'preview',
        'related_type',
        'related_id',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'preview' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Polish display label per type. Falls back to a humanised version
     * of the namespaced key so a brand-new type still renders sensibly.
     */
    public function label(): string
    {
        return match ($this->type) {
            'booking.requested' => 'Zgłoszenie rezerwacji',
            'booking.confirmed' => 'Potwierdzenie rezerwacji',
            'booking.cancelled' => 'Odwołanie rezerwacji',
            'booking.reminder' => 'Przypomnienie 24h',
            'booking.rescheduled' => 'Przesunięcie rezerwacji',
            'portal.magic_link' => 'Logowanie do portalu',
            default => str_replace(['.', '_'], [' · ', ' '], $this->type),
        };
    }
}
