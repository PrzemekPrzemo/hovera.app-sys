<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransporterProfile extends Model
{
    protected $connection = 'central';

    protected $table = 'transporter_profiles';

    protected $fillable = [
        'tenant_id', 'slug',
        'display_name', 'description', 'logo_path', 'cover_path',
        'contact_email', 'contact_phone', 'contact_website',
        'social_links', 'seo',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'social_links' => 'array',
            'seo' => 'array',
            'is_published' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
