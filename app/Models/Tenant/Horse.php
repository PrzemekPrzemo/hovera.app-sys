<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Horse extends TenantModel
{
    use SoftDeletes;

    protected $table = 'horses';

    protected $fillable = [
        'name', 'microchip', 'passport_number', 'ueln',
        'breed', 'sex', 'color', 'birth_date',
        'owner_client_id', 'box_id', 'cover_image_path',
        'notes', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'metadata' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'owner_client_id');
    }

    public function healthRecords(): HasMany
    {
        return $this->hasMany(HealthRecord::class);
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class);
    }

    public function boxAssignments(): HasMany
    {
        return $this->hasMany(BoxAssignment::class)->orderByDesc('assigned_at');
    }

    public function currentBoxAssignment(): ?BoxAssignment
    {
        return $this->boxAssignments()->whereNull('vacated_at')->first();
    }

    public function boardingServices(): BelongsToMany
    {
        return $this->belongsToMany(BoardingService::class, 'horse_boarding_services')
            ->withPivot(['price_override_cents', 'quantity', 'starts_at', 'ends_at', 'notes'])
            ->withTimestamps();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(StableActivity::class)->orderByDesc('performed_at');
    }

    public function weightMeasurements(): HasMany
    {
        return $this->hasMany(HorseWeightMeasurement::class)->orderByDesc('measured_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(HorseMessage::class)->orderByDesc('sent_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(HorseDocument::class)->orderBy('kind')->orderByDesc('created_at');
    }

    /**
     * Estymowany miesięczny koszt pensji = pensjonat boxa + suma usług
     * naliczanych miesięcznie i dziennie. Per-use / once usługi nie wchodzą,
     * bo są nieprzewidywalne.
     */
    public function estimatedMonthlyCostCents(): int
    {
        $boxRate = (int) ($this->box?->monthly_rate_cents ?? 0);

        $servicesTotal = 0;
        foreach ($this->boardingServices as $service) {
            $price = (int) ($service->pivot->price_override_cents ?? $service->price_cents);
            $qty = (float) ($service->pivot->quantity ?? 1);
            $servicesTotal += (int) round($price * $qty * $service->frequency->monthlyMultiplier());
        }

        return $boxRate + $servicesTotal;
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date?->diffInYears(now()) !== null
            ? (int) $this->birth_date->diffInYears(now())
            : null;
    }
}
