<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Singleton — jeden wiersz per tenant DB. Pobierany przez ::current(),
 * który auto-tworzy domyślny wiersz przy pierwszym dostępie.
 *
 * Nie używamy HasUlids tutaj — id jest auto-increment, bo jest dokładnie
 * jeden wiersz (singleton wzór). Centralna baza i tak izoluje tenanty
 * fizycznie, więc kolizje numeracji nie istnieją.
 */
class TransportSettings extends Model
{
    protected $connection = 'tenant';

    protected $table = 'transport_settings';

    protected $fillable = [
        'rate_per_km', 'rate_per_km_loaded', 'minimum_charge',
        'extra_horse_fee_default',
        'fuel_consumption_l_per_100km', 'fuel_surcharge_enabled', 'fuel_base_price_pln',
        'manual_fuel_price_pln',
        'vat_rate', 'currency',
        'home_address', 'home_lat', 'home_lng',
        'routing_provider',
        'ksef_token_encrypted', 'ksef_environment', 'ksef_nip', 'ksef_enabled',
        'default_payment_url_template', 'default_payment_method_label', 'payment_instructions',
        'p24_quote_autopay_enabled',
        'payu_quote_autopay_enabled',
    ];

    /**
     * Tokenu KSeF NIE pokazujemy nigdzie poza świadomym getKsefToken().
     * Serializowanie modelu (toArray / API / debug dump) musi go pomijać,
     * inaczej trafiłby do logów aplikacji albo telemetrii.
     *
     * @var list<string>
     */
    protected $hidden = ['ksef_token_encrypted'];

    protected function casts(): array
    {
        return [
            'rate_per_km' => 'decimal:2',
            'rate_per_km_loaded' => 'decimal:2',
            'minimum_charge' => 'decimal:2',
            'extra_horse_fee_default' => 'decimal:2',
            'fuel_consumption_l_per_100km' => 'decimal:2',
            'fuel_surcharge_enabled' => 'boolean',
            'fuel_base_price_pln' => 'decimal:2',
            'manual_fuel_price_pln' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'home_lat' => 'float',
            'home_lng' => 'float',
            'routing_provider' => 'array',
            'ksef_enabled' => 'boolean',
            'p24_quote_autopay_enabled' => 'boolean',
            'payu_quote_autopay_enabled' => 'boolean',
        ];
    }

    /**
     * Pobierz odszyfrowany token KSeF transportera. Zwraca null, jeśli
     * token nie został jeszcze ustawiony albo szyfrowanie zostało
     * zinstalowane na nowym kluczu (np. po rotacji APP_KEY) — wtedy
     * trzeba poprosić transportera o ponowne wprowadzenie tokenu.
     *
     * UWAGA: nigdy nie loguj wyniku. Jeśli musisz pokazać w logu fakt
     * obecności tokenu, użyj redactedTokenPreview().
     */
    public function getKsefToken(): ?string
    {
        $encrypted = $this->getRawOriginal('ksef_token_encrypted');
        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Szyfruje i zapisuje token. Pusty / null → kasuje token i wyłącza
     * integrację (defensive — nie zostawiamy częściowej konfiguracji).
     */
    public function setKsefToken(?string $token): void
    {
        if ($token === null || trim($token) === '') {
            $this->ksef_token_encrypted = null;
            $this->ksef_enabled = false;

            return;
        }

        $this->ksef_token_encrypted = Crypt::encryptString(trim($token));
    }

    /**
     * Bezpieczny do logowania podgląd tokenu — pierwsze 3 i ostatnie 3
     * znaki, reszta gwiazdki. Używany przez TransporterKsefService gdy
     * raportujemy błąd ops-om bez ujawniania pełnego sekretu.
     */
    public function redactedTokenPreview(): ?string
    {
        $token = $this->getKsefToken();
        if ($token === null) {
            return null;
        }
        if (strlen($token) <= 8) {
            return str_repeat('*', strlen($token));
        }

        return substr($token, 0, 3).str_repeat('*', 8).substr($token, -3);
    }

    /**
     * Domyślne wartości — używane przy auto-tworzeniu pierwszego wiersza.
     * Trzymane tu (nie tylko w migracji), bo Laravel firstOrCreate nie
     * polega na DEFAULT z DB, tylko inserts to co dostanie.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'rate_per_km' => 4.50,
            'rate_per_km_loaded' => null,
            'minimum_charge' => 800.00,
            'extra_horse_fee_default' => 0.00,
            'fuel_consumption_l_per_100km' => 32.5,
            'fuel_surcharge_enabled' => true,
            'fuel_base_price_pln' => 7.00,
            'vat_rate' => 23.00,
            'currency' => 'PLN',
            'routing_provider' => ['provider' => 'ors'],
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], self::defaults());
    }
}
