<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterAdDismissal extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected $table = 'master_ad_dismissals';

    protected $fillable = ['ad_id', 'user_id', 'dismissed_at'];

    protected function casts(): array
    {
        return ['dismissed_at' => 'datetime'];
    }

    public function ad(): BelongsTo
    {
        return $this->belongsTo(MasterAd::class, 'ad_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
