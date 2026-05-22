# ROLE MATRIX — autoryzacja w panelach Hovera

Single source of truth dla "kto może wejść gdzie". Punktem odniesienia jest:
- `app/Enums/TenantType.php` (3 typy tenanta)
- `app/Services/Tenancy/TenantRoleGate.php` (definicje grup ról)
- `app/Http/Middleware/RequireTenantType.php` (panel-level enforcement)

## Tenant types (`App\Enums\TenantType`)

| Type | Panel | Subscription | `canIssueInvoices()` | `isFreeTier()` |
|---|---|---|---|---|
| `Stable` | `/app` | PŁATNY | ✓ | ✗ |
| `Transporter` | `/transport` | PŁATNY | ✓ | ✗ |
| `HorseOwner` | `/owner` | FREE | ✗ | ✓ |

## Panel-level gating (middleware)

| Panel | Middleware | Wpis konfiguracji |
|---|---|---|
| `/admin` | `EnsureMasterAdmin` | `AdminPanelProvider` |
| `/app` | `RequireTenantType:stable` | `AppPanelProvider` |
| `/transport` | `RequireTenantType:transporter` | `TransportPanelProvider` |
| `/owner` | `RequireTenantType:horse_owner` | `OwnerPanelProvider` |

Master admin (`users.is_master_admin = true`) przechodzi przez wszystkie panele (impersonation w celach debug'u).

## Grupy ról (per tenant type)

### Stable (`/app`) — 7 ról

```
owner       — full access, jedyny może usunąć tenant
admin       — full access bez delete tenant
manager     — operations + finance, bez settings i team mgmt
instructor  — kalendarz, bookings, własne konie, klienci
employee    — log aktywności, kalendarz read-only, konie read-only
vet         — health records, konie, kalendarz, specjaliści
viewer      — read-only across operations
```

Grupy `TenantRoleGate::*`:

| Grupa | Role |
|---|---|
| `FULL_ADMINS` | owner, admin |
| `FULL_ADMINS_AND_MANAGERS` | owner, admin, manager |
| `HORSE_AND_CARE_STAFF` | owner, admin, manager, instructor, employee, vet, viewer |
| `STABLE_OPS_STAFF` | owner, admin, manager, instructor, viewer |
| `CLINICAL_WRITE_STAFF` | owner, admin, manager, vet |
| `FEED_STAFF` | owner, admin, manager, employee, viewer |
| `FINANCE_STAFF` | owner, admin, manager, viewer |
| `SPECIALIST_STAFF` | owner, admin, manager, vet, viewer |
| `RECURRING_CALENDAR_STAFF` | owner, admin, manager, instructor, vet, viewer |

### Transporter (`/transport`) — 4 role

```
owner    — full access, jedyny może usunąć tenant
admin    — full access bez delete tenant
operator — oferty + kalkulacje + faktury + przypisanie kierowców (bez settings/team/billing)
driver   — TYLKO swoje trasy + kalendarz + dokumenty
```

Grupy:

| Grupa | Role |
|---|---|
| `FULL_ADMINS` | owner, admin |
| `TRANSPORT_OPERATORS` | owner, admin, operator, manager (legacy) |
| `TRANSPORT_TEAM` | owner, admin, operator, manager, driver |
| `DRIVERS_ONLY` | driver |

### HorseOwner (`/owner`) — brak ról

Tenant single-user (FREE marketplace consumer). Brak memberships, brak ról. Wszystkie operacje scopowane do `tenant_id` w query (np. `Horse::where('tenant_id', current()->id)`).

## Resource/page → wymagane gates

### Panel `/app` (Stable)

| Page/Resource | `canIssueInvoices`? | Role gate |
|---|---|---|
| `KsefSettings` | ✓ wymaga | `FULL_ADMINS` (owner/admin) |
| `InvoicingSettings` | ✓ wymaga | `FULL_ADMINS` (owner/admin) — numeracja FV |
| `PaymentSettings` | ✓ wymaga | `FULL_ADMINS` (owner/admin) |
| `TenantSettings` | — | `FULL_ADMINS` |
| `TeamMemberResource` | — | `FULL_ADMINS` |
| `ClientResource` | — | `STABLE_OPS_STAFF` |
| `HorseResource` | — | `HORSE_AND_CARE_STAFF` |
| `InvoiceResource` | ✓ wymaga | `FINANCE_STAFF` |
| `Help` | — | wszyscy authenticated |

### Panel `/transport` (Transporter)

| Page/Resource | `canIssueInvoices`? | Role gate |
|---|---|---|
| `TransportSettings` | — | `FULL_ADMINS` |
| `MySubscription` | — | `FULL_ADMINS` |
| `LeadResource` | — | `TRANSPORT_OPERATORS` |
| `QuoteResource` | — | `TRANSPORT_OPERATORS` |
| `CustomerResource` | — | `TRANSPORT_OPERATORS` |
| `DriverResource` | — | `TRANSPORT_OPERATORS` |
| `VehicleResource` | — | `TRANSPORT_OPERATORS` |
| `TransportInvoiceResource` | ✓ wymaga | `TRANSPORT_OPERATORS` |
| `TransportDashboard` | — | wszyscy authenticated (counts są tenant-scoped) |
| `TransporterDocuments` | — | `FULL_ADMINS` |

### Panel `/owner` (HorseOwner)

Brak ról — gating tylko panel-level + query scope:

| Page/Resource | Gating |
|---|---|
| `HorseResource` | scope `where('owner_user_id', auth()->id())` |
| `TransportOrderResource` | scope per tenant |
| `FavoriteTransporterResource` | scope per tenant |
| `OrderTransport` | otwarta dla wszystkich w panelu |
| `InvoiceList`/`InvoiceShow` | scope per tenant — pokazuje FV otrzymane od stable |

## Krytyczne reguły anti-leakage (audit checklist)

1. **HorseOwner NIE widzi KSeF / Invoicing / Payment settings** — enforced przez `canIssueInvoices()` w `canAccess()` (PR #378).
2. **Stable user wpisany w URL `/transport`** — middleware redirect na `/app` (`RequireTenantType`).
3. **Transporter w URL `/app`** — middleware redirect na `/transport`.
4. **HorseOwner w URL `/app` lub `/transport`** — redirect na `/owner`.
5. **Master admin w dowolnym panelu** — przepuszczany (impersonation).

## Backward compat notes

- Legacy transporter tenants stworzeni przed wprowadzeniem `operator` role mogli mieć `manager` — `TRANSPORT_OPERATORS` zawiera oba dla kompatybilności.

## Powiązane testy

- `tests/Feature/Filament/FinanceSettingsHorseOwnerGatingTest.php` — gating finansów dla HorseOwner
- `tests/Feature/Filament/PanelAccessMatrixTest.php` (Faza B) — pełna macierz (tenant × user role × page)
