<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\TransporterDocumentType;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransporterDocument extends TenantModel
{
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'transporter_documents';

    protected $fillable = [
        'document_type', 'status',
        'file_path', 'file_size', 'file_mime', 'original_filename',
        'expires_at', 'issued_at',
        'verified_by_user_id', 'verified_at', 'rejection_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => TransporterDocumentType::class,
            'expires_at' => 'date',
            'issued_at' => 'date',
            'verified_at' => 'datetime',
            'file_size' => 'integer',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->expires_at !== null
            && $this->expires_at->isFuture()
            && $this->expires_at->isBefore(now()->addDays($days));
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED && ! $this->isExpired();
    }
}
