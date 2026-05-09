<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedStockMovement extends TenantModel
{
    protected $table = 'feed_stock_movements';

    protected $fillable = [
        'feed_item_id',
        'delta', 'kind', 'movement_date',
        'notes', 'user_central_id',
    ];

    protected function casts(): array
    {
        return [
            'delta' => 'decimal:2',
            'movement_date' => 'date',
        ];
    }

    public function feedItem(): BelongsTo
    {
        return $this->belongsTo(FeedItem::class);
    }

    public function kindLabel(): string
    {
        return __('app/feed_inventory.kind.'.$this->kind);
    }
}
