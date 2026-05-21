<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Tenant\TransporterDocument;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Tenant extends Model
{
    use HasUlids, SoftDeletes;

    protected $connection = 'central';

    protected $table = 'tenants';

    protected $fillable = [
        'slug', 'name', 'legal_name', 'tax_id', 'type',
        'verification_status', 'verified_at', 'verified_by_user_id', 'verification_notes',
        'is_featured', 'featured_at', 'featured_until', 'featured_by_user_id',
        'embed_allowed_origins', 'embed_api_token',
        'db_host', 'db_port', 'db_name', 'db_username', 'db_password_encrypted',
        'country', 'locale', 'timezone', 'currency',
        'plan_id', 'status', 'trial_ends_at',
        'trial_max_horses', 'trial_max_clients',
        'stripe_customer_id', 'stripe_subscription_id',
        'stripe_connect_account_id', 'stripe_connect_status', 'stripe_connect_onboarded_at',
        'current_period_ends_at', 'subscription_ends_at',
        'branding', 'settings',
        'custom_domain', 'custom_domain_verified_at',
        'terms_accepted_at', 'terms_version',
    ];

    protected function casts(): array
    {
        return [
            'type' => TenantType::class,
            'verification_status' => VerificationStatus::class,
            'verified_at' => 'datetime',
            'is_featured' => 'boolean',
            'featured_at' => 'datetime',
            'featured_until' => 'datetime',
            'embed_allowed_origins' => 'array',
            'embed_api_token' => 'encrypted',
            'branding' => 'array',
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'stripe_connect_onboarded_at' => 'datetime',
            'suspended_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'custom_domain_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'health_score' => 'integer',
            'db_port' => 'integer',
            'trial_max_horses' => 'integer',
            'trial_max_clients' => 'integer',
        ];
    }

    /**
     * Czy transporter ma zweryfikowane konto i może wystawiać oferty/FV,
     * widnieć na publicznym profilu i otrzymywać leady z marketplace'u.
     * Patrz docs/TRANSPORT.md (verification flow).
     *
     * Stable tenant'y nie mają tego flagu — zwracamy false (irrelevant).
     */
    public function isVerifiedTransporter(): bool
    {
        return $this->isTransporter()
            && $this->verification_status instanceof VerificationStatus
            && $this->verification_status->isVerified();
    }

    public function scopeStables(Builder $query): Builder
    {
        return $query->where('type', TenantType::Stable);
    }

    public function scopeTransporters(Builder $query): Builder
    {
        return $query->where('type', TenantType::Transporter);
    }

    public function scopeHorseOwners(Builder $query): Builder
    {
        return $query->where('type', TenantType::HorseOwner);
    }

    /**
     * Boost rankingowania publicznego — tenant pokazany na top'cie Top 10
     * landing'i `/transport` oraz katalogu `/przewoznicy`. Manualny toggle
     * przez master admina (TransporterResource action). Patrz docs/TRANSPORT.md §16.
     */
    public function scopeFeatured(Builder $query): Builder
    {
        // Featured aktywny gdy:
        //   - is_featured=true AND (featured_until IS NULL  // legacy permanent
        //                            OR featured_until > NOW())  // sponsored, nieprzeterminowany
        // Daily cron `transport:expire-featured` flipuje is_featured=false
        // gdy featured_until < NOW(), więc w praktyce odpowiednio.
        return $query->where('is_featured', true)
            ->where(function (Builder $q) {
                $q->whereNull('featured_until')->orWhere('featured_until', '>', now());
            });
    }

    /**
     * Master admin akcja "Polecany ON". Idempotentna — drugi toggle ON nie
     * resetuje featured_at (przydatne żeby utrzymać kolejność „dawno polecany").
     * Audit log wewnątrz TransporterResource action (kontekst master usera).
     */
    public function markFeatured(?string $byUserId = null): void
    {
        if ($this->is_featured) {
            return;
        }

        $this->forceFill([
            'is_featured' => true,
            'featured_at' => now(),
            'featured_by_user_id' => $byUserId,
        ])->save();
    }

    /**
     * Featured z terminem ważności — używane przez sponsored placements.
     * Po `featured_until` daily cron flipuje is_featured=false.
     * Extend'uje istniejący okres jeśli featured_until > now() — kup 30d
     * nad 30d → 60d razem (rolling extension).
     *
     * Patrz docs/TRANSPORT.md §16 (sponsored placements).
     */
    public function markFeaturedUntil(Carbon|\Illuminate\Support\Carbon $until, ?string $byUserId = null): void
    {
        // Rolling extension: gdy obecny featured_until > now(), pozostały
        // czas dolicza się do nowo kupionego pakietu (kup 30d nad aktywnym
        // 25d → 55d razem). Caller podaje `$until = now()+N`, my przeliczamy
        // jako "ile sekund od teraz" i dolicza do current lub od now().
        $now = now();
        $secondsToAdd = max(0, $until->getTimestamp() - $now->getTimestamp());

        $current = $this->featured_until;
        if ($current !== null && $current->isFuture()) {
            $newUntil = $current->copy()->addSeconds($secondsToAdd);
        } else {
            $newUntil = $now->copy()->addSeconds($secondsToAdd);
        }

        $this->forceFill([
            'is_featured' => true,
            'featured_at' => $this->featured_at ?? now(),
            'featured_until' => $newUntil,
            'featured_by_user_id' => $byUserId,
        ])->save();
    }

    public function unmarkFeatured(): void
    {
        if (! $this->is_featured) {
            return;
        }

        $this->forceFill([
            'is_featured' => false,
            'featured_at' => null,
            'featured_until' => null,
            'featured_by_user_id' => null,
        ])->save();
    }

    /**
     * Sprawdza czy podany origin jest na embed whitelist transportera.
     * Trim'uje trailing slash i normalizuje wielkość liter (browsers wysyłają
     * lowercase scheme + host). Patrz docs/TRANSPORT.md §16.
     */
    public function isEmbedOriginAllowed(string $origin): bool
    {
        $origins = $this->embed_allowed_origins ?? [];
        if (! is_array($origins) || $origins === []) {
            return false;
        }

        $normalised = rtrim(mb_strtolower(trim($origin)), '/');
        if ($normalised === '') {
            return false;
        }

        foreach ($origins as $allowed) {
            if (! is_string($allowed)) {
                continue;
            }
            if (rtrim(mb_strtolower(trim($allowed)), '/') === $normalised) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generuje nowy embed API token (32 bajty → 64 hex). Stary token jest
     * invalidated natychmiast — wszystkie istniejące embed'y na cudzych
     * stronach przestaną działać do momentu zaktualizowania snippet'u.
     */
    public function regenerateEmbedApiToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->embed_api_token = $token;
        $this->save();

        return $token;
    }

    public function isStable(): bool
    {
        return $this->type === TenantType::Stable;
    }

    public function isTransporter(): bool
    {
        return $this->type === TenantType::Transporter;
    }

    public function isHorseOwner(): bool
    {
        return $this->type === TenantType::HorseOwner;
    }

    /**
     * True when this tenant has a custom domain that has been verified
     * (DNS confirmed by the admin). Until verified, the middleware
     * ignores the column to prevent half-configured CNAMEs from blackholing.
     */
    public function hasVerifiedCustomDomain(): bool
    {
        return $this->custom_domain !== null && $this->custom_domain_verified_at !== null;
    }

    /**
     * Plan-level gate — only plans with `features.vanity_domain = true`
     * may set a custom domain. Master admin flips the feature in plans.
     */
    public function planAllowsVanityDomain(): bool
    {
        return (bool) data_get($this->plan?->features ?? [], 'vanity_domain', false);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(UserInvitation::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Stub relacja `documents()` — TransporterDocument żyje w per-tenant DB
     * i nie ma FK do central `tenants`, więc prawdziwej Eloquent relacji
     * NIE da się zdefiniować. Filament 3 jednak wymaga aby `RelationManager`
     * miał wywoływalną metodę o nazwie zadeklarowanej w
     * `protected static string $relationship = 'documents'`.
     *
     * Zwracamy whereRaw('1=0') — Filament dostaje typowo HasMany ale nigdy
     * nie złapie żadnego rekordu tym path'em (klasy `TransporterDocument`
     * używa się tylko po `TenantManager::setCurrent()` z poziomu Relation-
     * Managera; query budowane jest w jego `table()->query()` od zera).
     *
     * Patrz `TransporterResource\RelationManagers\TransporterDocumentsRelationManager`.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(TransporterDocument::class, 'tenant_id')
            ->whereRaw('1 = 0');
    }

    public function getDbPasswordAttribute(): string
    {
        return Crypt::decryptString($this->db_password_encrypted);
    }

    public function setDbPasswordAttribute(string $value): void
    {
        $this->attributes['db_password_encrypted'] = Crypt::encryptString($value);
    }

    /**
     * Connection config used to talk to this tenant's database.
     * Cached on the request via TenantManager — do not call hot.
     */
    public function databaseConnectionConfig(): array
    {
        return [
            'driver' => 'mysql',
            'host' => $this->db_host,
            'port' => $this->db_port,
            'database' => $this->db_name,
            'username' => $this->db_username,
            'password' => $this->db_password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => 'InnoDB',
        ];
    }

    /**
     * Statusy tenanta w których user MOŻE wejść do panelu. Identyczna
     * lista jak `TenantSelectorController::SELECTABLE_TENANT_STATUSES` —
     * trzymamy tylko w jednym miejscu źeby uniknąć redirect loop'ów
     * (InitialiseTenantFromSession ↔ TenantSelectorController).
     *
     * `provisioning` jest WAŻNY dla transporter tenants: tuż po signup
     * w `/przewoznicy/dolacz` tenant ma status='provisioning' (czeka
     * na master admin verification). Bez `provisioning` w tej liście
     * impersonacja "Login as transporter" wpadała w infinite redirect:
     * /transport/dashboard → !isUsable → /tenant/select → set → /transport
     * → /transport/dashboard ...
     */
    public const PANEL_ACCESSIBLE_STATUSES = ['provisioning', 'trialing', 'active', 'past_due'];

    public function isUsable(): bool
    {
        return in_array($this->status, self::PANEL_ACCESSIBLE_STATUSES, true);
    }

    /**
     * Uruchamia trial 1-miesięczny w momencie pozytywnej weryfikacji dokumentów
     * przez master admin'a. Marketing spec (hovera.app/produkt/transport/):
     * "1 miesiąc gratis NIE od signupu — od momentu pozytywnej weryfikacji
     * dokumentów". Idempotentne — drugie wywołanie nie resetuje daty.
     *
     * Wywoływane z `TransporterResource::verify()` PO `verification_status=Verified`.
     * Dla stable tenantów / niezweryfikowanych transporterów — no-op.
     */
    public function startTrialOnVerification(): void
    {
        if (! $this->isTransporter() || ! $this->isVerifiedTransporter()) {
            return;
        }

        // Idempotentne — jeśli trial już ustawiony (np. ręcznie przez admin'a
        // albo z legacy CreateTenant), nie nadpisujemy.
        if ($this->trial_ends_at !== null) {
            return;
        }

        $this->forceFill([
            'trial_ends_at' => now()->addDays(30),
            // Status `trialing` żeby `isUsable()` zwracał true i tenant
            // mógł logować się do panelu.
            'status' => $this->status === 'provisioning' ? 'trialing' : $this->status,
        ])->save();
    }

    /**
     * Czy ten tenant ma dostęp do modułu transportowego (kalkulator, oferty,
     * leady, marketplace). Marketing spec: stables dostają moduł BEZPŁATNIE
     * w ramach swojego planu Hovery (z wyjątkiem `free`); transporterzy
     * potrzebują własnego planu transport_*.
     *
     * Wywoływane jako gate w `app/Filament/Transport/Pages/*` oraz w
     * `PublicTransportInquiry` (formularz publiczny — bez gate).
     */
    public function canUseTransport(): bool
    {
        if (! $this->isUsable()) {
            return false;
        }

        if ($this->isTransporter()) {
            // Transporter musi mieć plan z `audience=transporter` (czyli
            // transport_start / transport_pro / transport_business /
            // transport_enterprise — albo legacy). Plan_id ≠ null wystarczy
            // bo CreateTenant gwarantuje plan z audience=transporter.
            return $this->plan_id !== null;
        }

        if ($this->isStable()) {
            // Stable na planie free → upgrade required. Każdy inny stable
            // plan (solo/stable/pro/enterprise) dostaje moduł transport
            // w cenie swojego planu Hovery.
            $code = (string) ($this->plan?->code ?? '');

            return $code !== '' && $code !== 'free';
        }

        return false;
    }

    /**
     * True when the trial has ended AND no Stripe subscription is bound —
     * i.e. the tenant must pick a paid plan to keep using the panel.
     * Master admins / Free plan are checked higher up the call stack.
     */
    public function trialHasExpired(): bool
    {
        if ($this->stripe_subscription_id !== null) {
            return false;
        }

        return $this->trial_ends_at !== null
            && $this->trial_ends_at->isPast();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Stripe Connect Express — transporter ma własne konto Stripe (KYC u Stripe,
     * pieniądze trafiają bezpośrednio do niego). Patrz docs/TRANSPORT.md §15.6.
     * Hovera = platforma; transporter = merchant of record.
     */
    public function hasStripeConnectEnabled(): bool
    {
        return $this->isTransporter()
            && $this->stripe_connect_account_id !== null
            && $this->stripe_connect_status === 'enabled';
    }

    /**
     * Aktualne limity planu z poprawką na trial — tenant w trialu
     * ma WSZYSTKIE feature'y wybranego planu, ale konie/pojazdy/klienci
     * są przycięte do trial_*. Po flipie statusu na `active` (Stripe
     * webhook), limity przepadają i schodzimy do pełnej tabeli z `plan->limits`.
     *
     * Zwraca tablicę z kluczami:
     *   - max_horses, max_clients, max_users, max_storage_mb
     *   - max_vehicles, max_drivers (transporter)
     * `-1` traktowane jako unlimited (jak w PlansSeeder).
     *
     * @return array<string,int>
     */
    public function effectiveLimits(): array
    {
        $planLimits = $this->plan?->limits ?? [];

        $defaults = [
            'max_horses' => (int) ($planLimits['max_horses'] ?? 0),
            'max_clients' => (int) ($planLimits['max_clients'] ?? 0),
            'max_users' => (int) ($planLimits['max_users'] ?? 0),
            'max_storage_mb' => (int) ($planLimits['max_storage_mb'] ?? 0),
            'max_vehicles' => (int) ($planLimits['max_vehicles'] ?? 0),
            'max_drivers' => (int) ($planLimits['max_drivers'] ?? 0),
        ];

        if ($this->status !== 'trialing') {
            return $defaults;
        }

        if ($this->trial_max_horses !== null) {
            $defaults['max_horses'] = (int) $this->trial_max_horses;
        }
        if ($this->trial_max_clients !== null) {
            $defaults['max_clients'] = (int) $this->trial_max_clients;
        }

        return $defaults;
    }
}
