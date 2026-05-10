<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    protected $connection = 'tenant';

    protected $table = 'idempotency_keys';

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'key', 'user_central_id', 'entity', 'op', 'response_json', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
