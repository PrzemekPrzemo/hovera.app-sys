<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantMessage extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected $table = 'tenant_messages';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'sent_by_user_id',
        'template',
        'subject',
        'body',
        'recipients_count',
        'recipients',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'recipients' => 'array',
            'sent_at' => 'datetime',
            'recipients_count' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }
}
