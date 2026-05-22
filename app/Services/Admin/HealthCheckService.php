<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Central\NbpExchangeRate;
use App\Models\Central\SystemSetting;
use App\Services\CompanyLookup\CeidgApiService;
use App\Services\CompanyLookup\GusApiService;
use App\Services\CompanyLookup\ViesService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Status integracji zewnętrznych — czyta szybko (configured / not),
 * opcjonalnie pinguje live na żądanie usera (`pingX()` metody).
 *
 * Używane przez `App\Filament\Admin\Pages\HealthChecks`.
 *
 * Convention dla statusu:
 *   - 'ok'           — skonfigurowane + (jeśli było ping) odpowiada
 *   - 'not_configured' — brak creds w SystemSetting / env
 *   - 'degraded'     — skonfigurowane ale ostatni ping failure / stary cache
 *   - 'error'        — wyjątek przy ping (network, timeout, auth)
 *   - 'unknown'      — nie zostało jeszcze sprawdzone (cache miss bez pingu)
 *
 * Wszystkie pingi mają krótki timeout — nie blokujemy admin UI ponad
 * potrzebę. Catch Throwable → degraded/error, nigdy uncaught exception.
 */
class HealthCheckService
{
    public function __construct(
        private readonly GusApiService $gus,
        private readonly CeidgApiService $ceidg,
        private readonly ViesService $vies,
    ) {}

    /**
     * Wszystkie integracje — instant check (bez live ping). Używane przy
     * mount stronicy żeby pokazać początkowy widok bez czekania.
     *
     * @return list<array{key:string, label:string, status:string, detail:?string}>
     */
    public function snapshot(): array
    {
        return [
            $this->gusStatus(live: false),
            $this->ceidgStatus(live: false),
            $this->viesStatus(live: false),
            $this->nbpStatus(),
            $this->ksefCentralStatus(),
            $this->smtpStatus(live: false),
            $this->databaseStatus(),
        ];
    }

    /**
     * @return array{key:string, label:string, status:string, detail:?string}
     */
    public function gusStatus(bool $live = true): array
    {
        if (! $this->gus->isConfigured()) {
            return $this->row('gus', 'GUS BIR (REGON)', 'not_configured',
                __('admin/health_checks.detail.not_configured_gus'));
        }
        if (! $live) {
            return $this->row('gus', 'GUS BIR (REGON)', 'ok',
                __('admin/health_checks.detail.configured'));
        }

        try {
            // 1234567890 — niepoprawny NIP, ale wystarczy żeby sprawdzić
            // czy API key działa (zwróci błąd biznesowy a nie auth error).
            $this->gus->findByNip('1234567890');

            return $this->row('gus', 'GUS BIR (REGON)', 'ok',
                __('admin/health_checks.detail.live_ok'));
        } catch (Throwable $e) {
            return $this->row('gus', 'GUS BIR (REGON)', 'error', $e->getMessage());
        }
    }

    /**
     * @return array{key:string, label:string, status:string, detail:?string}
     */
    public function ceidgStatus(bool $live = true): array
    {
        if (! $this->ceidg->isConfigured()) {
            return $this->row('ceidg', 'CEIDG', 'not_configured',
                __('admin/health_checks.detail.not_configured_ceidg'));
        }
        if (! $live) {
            return $this->row('ceidg', 'CEIDG', 'ok',
                __('admin/health_checks.detail.configured'));
        }
        try {
            $this->ceidg->findByNip('1234567890');

            return $this->row('ceidg', 'CEIDG', 'ok',
                __('admin/health_checks.detail.live_ok'));
        } catch (Throwable $e) {
            return $this->row('ceidg', 'CEIDG', 'error', $e->getMessage());
        }
    }

    /**
     * @return array{key:string, label:string, status:string, detail:?string}
     */
    public function viesStatus(bool $live = true): array
    {
        // VIES jest public API — nie wymaga konfiguracji. Sprawdzamy
        // override base_url w SystemSetting (opcjonalne). Defensive cast
        // przez `stringSetting()` — gdyby ktoś przypadkiem zapisał array,
        // nie wybuchamy "Array to string conversion".
        $baseUrl = self::stringSetting(SystemSetting::getValue('vies.base_url', ''));
        $detail = $baseUrl !== '' ? __('admin/health_checks.detail.vies_custom_url', ['url' => $baseUrl])
            : __('admin/health_checks.detail.vies_default');

        if (! $live) {
            return $this->row('vies', 'VIES (EU VAT)', 'ok', $detail);
        }

        try {
            // PL 5260250274 — znany prawidłowy NIP (Sendormeco) — VIES powinno
            // zwrócić valid=true. Inny status = problem z API.
            $result = $this->vies->validate('PL', '5260250274');
            if ($result === null) {
                return $this->row('vies', 'VIES (EU VAT)', 'error',
                    __('admin/health_checks.detail.live_no_response'));
            }

            return $this->row('vies', 'VIES (EU VAT)', 'ok',
                __('admin/health_checks.detail.live_ok').' · '.$detail);
        } catch (Throwable $e) {
            return $this->row('vies', 'VIES (EU VAT)', 'error', $e->getMessage());
        }
    }

    /**
     * NBP — bez live ping (NBP API jest stable). Pokazujemy timestamp
     * ostatniego cached entry per currency.
     *
     * @return array{key:string, label:string, status:string, detail:?string}
     */
    public function nbpStatus(): array
    {
        $latest = NbpExchangeRate::query()
            ->orderByDesc('created_at')
            ->first();

        if ($latest === null) {
            return $this->row('nbp', 'NBP (kursy walut)', 'unknown',
                __('admin/health_checks.detail.nbp_no_cache'));
        }

        $age = Carbon::parse($latest->created_at)->diffInHours(now());
        $status = $age < 48 ? 'ok' : 'degraded';

        return $this->row('nbp', 'NBP (kursy walut)', $status,
            __('admin/health_checks.detail.nbp_last_sync', [
                'code' => (string) $latest->currency_code,
                'date' => Carbon::parse($latest->created_at)->format('Y-m-d H:i'),
            ]));
    }

    /**
     * KSeF central — sprawdzenie czy master admin wgrał certyfikat
     * dla wystawiania FV Hovera (subskrypcje stajni).
     *
     * @return array{key:string, label:string, status:string, detail:?string}
     */
    public function ksefCentralStatus(): array
    {
        $hasNip = ! empty(SystemSetting::getValue('ksef_central.nip')) || ! empty(config('services.ksef_central.context_nip'));
        $hasCert = ! empty(SystemSetting::getSecret('ksef_central.cert_pfx'))
            || ! empty(SystemSetting::getSecret('ksef_central.cert_pem'));

        if (! $hasNip || ! $hasCert) {
            return $this->row('ksef_central', 'KSeF (central — FV Hovera)', 'not_configured',
                __('admin/health_checks.detail.ksef_central_missing'));
        }

        return $this->row('ksef_central', 'KSeF (central — FV Hovera)', 'ok',
            __('admin/health_checks.detail.configured'));
    }

    /**
     * SMTP — sprawdzenie czy master admin skonfigurował hosta i credsy.
     * Live ping przez `Mail::raw()` jest opcjonalny (wymaga adresu testowego).
     *
     * @return array{key:string, label:string, status:string, detail:?string}
     */
    public function smtpStatus(bool $live = false): array
    {
        // SMTP host w SystemSetting jest zapisany przez `setSecret()` —
        // wrap `['__crypt' => ...]`. Trzeba `getSecret()` zeby odszyfrowac,
        // nie `getValue()` (zwraca array i (string)cast wybucha
        // "Array to string conversion"). Patrz SmtpSettings.php:79.
        $host = self::stringSetting(SystemSetting::getSecret('mail.default.host', ''));
        if ($host === '') {
            $host = self::stringSetting(config('mail.mailers.smtp.host'));
        }
        if ($host === '') {
            return $this->row('smtp', 'SMTP (poczta)', 'not_configured',
                __('admin/health_checks.detail.smtp_no_host'));
        }

        // Live ping wymaga adresu testowego — pomijamy w default snapshot.
        // Real ping przez dedykowaną akcję na SmtpSettings page.
        return $this->row('smtp', 'SMTP (poczta)', 'ok',
            __('admin/health_checks.detail.smtp_host', ['host' => $host]));
    }

    /**
     * @return array{key:string, label:string, status:string, detail:?string}
     */
    public function databaseStatus(): array
    {
        try {
            DB::connection('central')->selectOne('SELECT 1 as ok');

            return $this->row('db_central', __('admin/health_checks.label.db_central'), 'ok',
                __('admin/health_checks.detail.db_responding'));
        } catch (Throwable $e) {
            return $this->row('db_central', __('admin/health_checks.label.db_central'), 'error', $e->getMessage());
        }
    }

    /**
     * @return array{key:string, label:string, status:string, detail:?string}
     */
    private function row(string $key, string $label, string $status, ?string $detail): array
    {
        return ['key' => $key, 'label' => $label, 'status' => $status, 'detail' => $detail];
    }

    /**
     * Defensive cast SystemSetting value → string. Gdy ktoś przypadkowo
     * zapisze array (np. zapomni `setSecret` vs `setValue`), nie wybuchamy
     * "Array to string conversion" — zwracamy ''.
     */
    private static function stringSetting(mixed $value): string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return '';
        }

        return (string) $value;
    }
}
