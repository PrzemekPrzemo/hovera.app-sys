<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Klient transportera — per-tenant DB. Przy tworzeniu oferty można wybrać
 * istniejącego klienta (search po name/company/tax_id) albo dodać nowego.
 * Dane mogą być zweryfikowane w MF Biała Lista (NIP) lub KRS.
 *
 * Quote nadal trzyma snapshot customer_* (historyczna dokładność na ofertach
 * sprzed edycji klienta), `customer_id` jest tylko backlink'iem.
 */
class Customer extends TenantModel
{
    use SoftDeletes;

    protected $table = 'customers';

    protected $fillable = [
        'name', 'email', 'phone',
        'company', 'tax_id', 'krs_number', 'address',
        'source',
        'last_verified_at', 'verification_source', 'verification_data',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'last_verified_at' => 'datetime',
            'verification_data' => 'array',
        ];
    }

    public function displayLabel(): string
    {
        return $this->company !== null && $this->company !== ''
            ? "{$this->name} — {$this->company}"
            : $this->name;
    }
}
