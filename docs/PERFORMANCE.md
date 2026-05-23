# Hovera — performance & N+1 baseline

Krótki przewodnik po wydajności DB. Cel: każdy nowy resource / job / view ma
eager loading "z marszu", a regresje wpadają jeszcze przed merge'em.

## TL;DR — checklist dla każdego PR-a

- [ ] Nowy Filament `Resource` → ma `modifyQueryUsing(fn (Builder $q) => $q->with([...]))` w `table()` jeśli kolumny używają `.relation.field`
- [ ] Nowa Eloquent collection → albo `$with` w modelu, albo `->with()` w query — nigdy lazy w pętli
- [ ] Nowy job iterujący tysiące rekordów → `chunkById(500, fn ...)` zamiast `->get()->each(...)`
- [ ] Nowy raport / agregat → użyj `withCount()`, `withSum()` zamiast policzenia w PHP
- [ ] Nowy public endpoint → `php artisan optimize` w deploy script musi go też cache'ować

## Lokalne wykrywanie N+1 — opt-in

W `.env.local`:

```bash
HOVERA_PREVENT_LAZY_LOADING=true
HOVERA_SLOW_QUERY_LOG_MS=50
```

- `HOVERA_PREVENT_LAZY_LOADING=true` → każdy lazy `$model->relation` rzuca `Illuminate\Database\LazyLoadingViolationException`. Catch przed deployem.
- `HOVERA_SLOW_QUERY_LOG_MS=50` → każde query > 50ms idzie do default log channel z SQL + bindings + connection. Po sesji ustaw z powrotem na `0`.

**Nigdy nie włączaj `HOVERA_PREVENT_LAZY_LOADING` w produkcji** — użytkownik dostanie 500 zamiast wolniejszego renderu. To tool dla devów.

## CI gate (opcjonalnie)

Test suite można puścić ze strict lazy load, żeby catchować regresje:

```bash
HOVERA_PREVENT_LAZY_LOADING=true vendor/bin/phpunit
```

Obecnie suite **nie jest** czysty na strict mode (kilka testów lazy-loaduje). Po wyprostowaniu wpiąć do CI jako osobny job.

## Patterns w tym repo

### Filament Resources — eager loading

15/17 resource'ów w `app/Filament/App/Resources/` już ma `modifyQueryUsing(...)`. Wzorzec:

```php
public static function table(Table $table): Table
{
    return $table
        ->modifyQueryUsing(fn (Builder $query) => $query->with(['horse', 'client']))
        ->columns([
            TextColumn::make('horse.name')->label(...),
            TextColumn::make('client.name')->label(...),
            ...
        ]);
}
```

Bez tego każdy wiersz tabeli = 2 dodatkowe queries (1 koń + 1 klient) × paginate(20) = 41 queries zamiast 3.

### Modele z naturalnym `$with`

Dla relacji, które są **prawie zawsze** potrzebne (np. `Invoice → items`), użyj `$with` w modelu:

```php
class Invoice extends TenantModel
{
    protected $with = ['items'];
}
```

Trade-off: zawsze ładuje, nawet gdy nieużywane. Włącz tylko gdy stats z `EXPLAIN` pokazują, że relacja jest > 80% requestów.

### Jobs — chunkBy zamiast get

```php
// ŹLE — load 50k rows do pamięci
Horse::all()->each(fn ($h) => $h->recompute());

// DOBRZE — paczki po 500, niski memory footprint
Horse::query()->chunkById(500, function ($horses) {
    foreach ($horses as $h) {
        $h->recompute();
    }
});
```

`chunkById` (a nie `chunk`) jest istotne — `chunk` używa LIMIT/OFFSET które bredzi gdy updatujemy rekordy w trakcie iteracji. `chunkById` używa `WHERE id > ?` co jest deterministyczne.

### Counts — withCount

```php
// ŹLE — N+1 (jeden COUNT per stable)
$stables->each(fn ($s) => $s->horses_count = $s->horses()->count());

// DOBRZE — jeden JOIN
$stables = Stable::query()->withCount('horses')->get();
```

### Tenant queries — pamiętaj o connection

Wszystko w `App\Models\Tenant\*` używa connection `tenant`, ale `DB::table(...)` defaultuje do `mysql`. Zawsze:

```php
DB::connection('tenant')->table('clients')->where(...)->get();
```

albo użyj modelu.

## Inspekcja runtime

### `php artisan tinker` z query log

```php
DB::connection('tenant')->enableQueryLog();
$result = SomeService::run(...);
collect(DB::connection('tenant')->getQueryLog())->dd();
```

### `php artisan db:show --connection=tenant`

Lista tabel + counts + size. Szybki sanity check po migracji.

### Tenant export → mapa wolumenu

`php artisan tenant:export {ulid}` wypluwa `_manifest.json` z liczbą rekordów per tabela. Jak `calendar_entries: 50000` → priorytet do indeksu / chunkowania w jobach na tym tenancie.

## Znane hotspoty (audit 2026-05)

| Miejsce | Problem | Status |
|---|---|---|
| Filament Owner panel — wszystkie 17 App Resources | 15/17 ma eager load | ✓ healthy |
| Tenant snapshot health (`SnapshotTenantHealthCommand`) | per-tenant set + reset, OK na hundreds, watch przy thousands | ✓ acceptable |
| `transport:ksef:poll-submitted` co 30 min | iteruje submitted invoices via chunkById | ✓ healthy |

Po dodaniu nowego hotspotu — uzupełnij tabelę.
