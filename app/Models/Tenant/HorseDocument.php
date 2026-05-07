<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\HorseDocumentKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HorseDocument extends TenantModel
{
    use SoftDeletes;

    protected $table = 'horse_documents';

    protected $fillable = [
        'horse_id', 'name', 'kind', 'description',
        'file_path', 'original_name', 'mime', 'size_bytes',
        'uploaded_by_role', 'uploaded_by_user_id', 'uploaded_by_client_id',
        'valid_from', 'valid_until', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'kind' => HorseDocumentKind::class,
            'size_bytes' => 'integer',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'metadata' => 'array',
        ];
    }

    public function horse(): BelongsTo
    {
        return $this->belongsTo(Horse::class);
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if ($this->valid_until === null) {
            return false;
        }
        if ($this->valid_until->isPast()) {
            return false;
        }

        return $this->valid_until->lte(now()->addDays($days));
    }

    public function uploadedByStable(): bool
    {
        return $this->uploaded_by_role === 'stable';
    }

    public function uploadedByClient(): bool
    {
        return $this->uploaded_by_role === 'client';
    }

    public function sizeFormatted(): string
    {
        $bytes = (int) $this->size_bytes;
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1, ',', ' ').' KB';
        }

        return number_format($bytes / (1024 * 1024), 1, ',', ' ').' MB';
    }

    public function scopeForHorse(Builder $query, string $horseId): Builder
    {
        return $query->where('horse_id', $horseId);
    }

    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        return $query
            ->whereNotNull('valid_until')
            ->where('valid_until', '>=', now()->toDateString())
            ->where('valid_until', '<=', now()->addDays($days)->toDateString());
    }

    public function scopeUploadedByClient(Builder $query, string $clientId): Builder
    {
        return $query->where('uploaded_by_client_id', $clientId);
    }
}
