<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends TenantModel
{
    use SoftDeletes;

    protected $table = 'drivers';

    protected $fillable = [
        'central_user_id',
        'first_name', 'last_name', 'email', 'phone',
        'license_number', 'license_categories', 'license_expires_at',
        'has_animal_transport_cert', 'animal_transport_cert_expires_at',
        'has_adr', 'adr_expires_at',
        'date_of_birth', 'hire_date',
        'notes',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'license_categories' => 'array',
            'license_expires_at' => 'date',
            'animal_transport_cert_expires_at' => 'date',
            'adr_expires_at' => 'date',
            'date_of_birth' => 'date',
            'hire_date' => 'date',
            'has_animal_transport_cert' => 'boolean',
            'has_adr' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(
            fn () => trim($this->first_name.' '.$this->last_name),
        );
    }
}
