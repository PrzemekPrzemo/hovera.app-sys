<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Tenant\Client;
use App\Models\Tenant\Horse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Excel/CSV import service — clients + horses.
 *
 * Handles parsing of `.xlsx`, `.xls`, `.csv` files (UTF-8 + Windows-1250 BOM),
 * validates rows against per-entity rules, and bulk-inserts via the active
 * `tenant` DB connection. Used by the ImportWizard Filament page.
 *
 * Supported entities: `clients`, `horses`. Stables migrating from
 * Nasza Stajnia / Horstable / generic Excel exports use this to bring
 * existing records into hovera without manual re-entry.
 */
class ExcelImportService
{
    public const ENTITY_CLIENTS = 'clients';

    public const ENTITY_HORSES = 'horses';

    /** Cap rows scanned to keep memory bounded. */
    public const MAX_ROWS = 10_000;

    /**
     * Mapping from canonical model field → list of header aliases (Polish + English).
     * Lower-cased, trimmed, accent-preserving comparison.
     *
     * @var array<string, array<string, list<string>>>
     */
    public const HEADER_ALIASES = [
        self::ENTITY_CLIENTS => [
            'first_name' => ['imię', 'imie', 'first name', 'firstname', 'name'],
            'last_name' => ['nazwisko', 'last name', 'lastname', 'surname'],
            'email' => ['email', 'e-mail', 'mail'],
            'phone' => ['telefon', 'tel', 'phone', 'numer', 'numer telefonu'],
            'street' => ['ulica', 'street', 'adres', 'address'],
            'postal_code' => ['kod pocztowy', 'kod', 'postal code', 'zip', 'postcode'],
            'city' => ['miasto', 'miejscowość', 'miejscowosc', 'city'],
            'tax_id' => ['nip', 'tax id', 'vat'],
            'notes' => ['notatki', 'uwagi', 'notes', 'comment', 'comments'],
        ],
        self::ENTITY_HORSES => [
            'name' => ['imię konia', 'imie konia', 'imię', 'imie', 'nazwa', 'horse name', 'name'],
            'breed' => ['rasa', 'breed'],
            'sex' => ['płeć', 'plec', 'gender', 'sex'],
            'color' => ['maść', 'masc', 'maść konia', 'color', 'colour'],
            'birth_date' => ['data urodzenia', 'rok urodzenia', 'birth date', 'dob', 'born'],
            'microchip' => ['mikroczip', 'chip', 'microchip'],
            'passport_number' => ['paszport', 'numer paszportu', 'passport', 'passport number'],
            'client_email' => ['email właściciela', 'mail właściciela', 'owner email', 'właściciel', 'wlasciciel', 'email wlasciciela'],
            'notes' => ['notatki', 'uwagi', 'notes'],
        ],
    ];

    /** Sex aliases accepted in import → canonical enum value on the model. */
    private const SEX_ALIASES = [
        'klacz' => 'mare', 'mare' => 'mare', 'k' => 'mare', 'm' => 'mare',
        'ogier' => 'stallion', 'stallion' => 'stallion', 'o' => 'stallion',
        'wałach' => 'gelding', 'walach' => 'gelding', 'gelding' => 'gelding', 'w' => 'gelding', 'g' => 'gelding',
        'klaczka' => 'filly', 'filly' => 'filly',
        'ogierek' => 'colt', 'colt' => 'colt',
        'źrebak' => 'foal', 'zrebak' => 'foal', 'foal' => 'foal',
    ];

    /**
     * Parse a freshly-uploaded spreadsheet into headers + rows.
     *
     * @return array{headers: list<string>, rows: list<array<int, scalar|null>>}
     */
    public function parseFile(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'csv') {
            $reader = new CsvReader;
            $reader->setInputEncoding(self::detectCsvEncoding($path));
            // Auto-detect comma vs semicolon (Polish Excel default).
            $reader->setDelimiter(self::detectCsvDelimiter($path));
            $spreadsheet = $reader->load($path);
        } else {
            $spreadsheet = IOFactory::load($path);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = min($sheet->getHighestDataRow(), self::MAX_ROWS + 1);
        $highestColumn = $sheet->getHighestDataColumn();

        $data = $sheet->rangeToArray("A1:{$highestColumn}{$highestRow}", null, false, false);

        // Filter trailing fully-empty rows (PhpSpreadsheet pads on .xlsx).
        $data = array_values(array_filter(
            $data,
            static fn (array $row): bool => count(array_filter($row, static fn ($v) => $v !== null && $v !== '')) > 0
        ));

        if ($data === []) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = array_map(
            static fn ($v): string => self::stripBom(is_scalar($v) ? trim((string) $v) : ''),
            $data[0]
        );

        $rows = array_slice($data, 1);

        return ['headers' => $headers, 'rows' => array_values($rows)];
    }

    /**
     * Suggest a header→field mapping based on the alias dictionary. Returns
     * an array keyed by canonical field with the matched header (or null).
     *
     * @param  list<string>  $headers
     * @return array<string, string|null>
     */
    public function suggestMapping(string $entity, array $headers): array
    {
        $suggestions = [];
        $normalized = [];
        foreach ($headers as $h) {
            $normalized[$h] = self::normalize($h);
        }

        foreach (self::HEADER_ALIASES[$entity] ?? [] as $field => $aliases) {
            $match = null;
            foreach ($aliases as $alias) {
                $aliasNorm = self::normalize($alias);
                foreach ($normalized as $original => $hNorm) {
                    if ($hNorm === $aliasNorm) {
                        $match = $original;
                        break 2;
                    }
                }
            }
            $suggestions[$field] = $match;
        }

        return $suggestions;
    }

    /**
     * Validate one mapped row. Returns ['ok' => bool, 'data' => mapped, 'errors' => [...]].
     *
     * @param  array<string, string|null>  $mapping  field → header name (or null = skip)
     * @param  array<int, scalar|null>     $row       raw row values
     * @param  list<string>                $headers
     * @return array{ok: bool, data: array<string, mixed>, errors: list<string>}
     */
    public function validateRow(string $entity, array $mapping, array $row, array $headers): array
    {
        $headerIndex = array_flip($headers);
        $data = [];
        foreach ($mapping as $field => $header) {
            if ($header === null || $header === '' || ! isset($headerIndex[$header])) {
                continue;
            }
            $value = $row[$headerIndex[$header]] ?? null;
            $data[$field] = is_string($value) ? trim($value) : $value;
        }

        $errors = [];
        if ($entity === self::ENTITY_CLIENTS) {
            // Build a "name" out of first/last if user split them.
            if (! isset($data['name']) || $data['name'] === '' || $data['name'] === null) {
                $first = trim((string) ($data['first_name'] ?? ''));
                $last = trim((string) ($data['last_name'] ?? ''));
                $name = trim($first.' '.$last);
                if ($name === '') {
                    $errors[] = 'Brak imienia/nazwiska klienta.';
                } else {
                    $data['name'] = $name;
                }
            }
            unset($data['first_name'], $data['last_name']);

            if (! empty($data['email']) && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Nieprawidłowy adres e-mail: '.$data['email'];
            }
        } elseif ($entity === self::ENTITY_HORSES) {
            if (empty($data['name'])) {
                $errors[] = 'Brak imienia konia.';
            }
            if (! empty($data['birth_date'])) {
                $parsed = self::parseDate($data['birth_date']);
                if ($parsed === null) {
                    $errors[] = 'Nieprawidłowa data urodzenia: '.$data['birth_date'];
                } else {
                    $data['birth_date'] = $parsed->format('Y-m-d');
                }
            }
            if (! empty($data['sex'])) {
                $key = self::normalize((string) $data['sex']);
                $data['sex'] = self::SEX_ALIASES[$key] ?? null;
                if ($data['sex'] === null) {
                    $errors[] = 'Nieznana płeć: '.$key;
                }
            }
            if (! empty($data['client_email']) && ! filter_var($data['client_email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Nieprawidłowy email właściciela: '.$data['client_email'];
            }
        } else {
            $errors[] = 'Nieobsługiwany typ importu: '.$entity;
        }

        return ['ok' => $errors === [], 'data' => $data, 'errors' => $errors];
    }

    /**
     * Run the import. Wraps everything in a tenant DB transaction; rolls back
     * on hard DB errors but skips individually-invalid rows so a partial
     * import survives. Caller decides via the wizard preview whether to proceed.
     *
     * @param  array<string, string|null>  $mapping
     * @param  list<array<int, scalar|null>>  $rows
     * @param  list<string>  $headers
     * @return array{imported:int, failed:int, errors: list<array{row:int, message:string}>}
     */
    public function import(string $entity, array $mapping, array $rows, array $headers): array
    {
        $imported = 0;
        $failed = 0;
        $errors = [];

        // Pre-load existing emails / horse names for dedupe.
        if ($entity === self::ENTITY_CLIENTS) {
            $existingEmails = Client::query()
                ->whereNotNull('email')
                ->pluck('email')
                ->map(static fn ($e) => strtolower((string) $e))
                ->all();
            $existingEmails = array_flip($existingEmails);
        } else {
            $existingEmails = [];
        }

        $clientByEmail = []; // resolved client_id cache for horse imports

        DB::connection('tenant')->transaction(function () use (
            $entity, $mapping, $rows, $headers,
            &$imported, &$failed, &$errors, &$existingEmails, &$clientByEmail,
        ): void {
            foreach ($rows as $i => $row) {
                $rowNumber = $i + 2; // +2: header is row 1, data starts on 2 (1-indexed for the user)
                $result = $this->validateRow($entity, $mapping, $row, $headers);
                if (! $result['ok']) {
                    $failed++;
                    foreach ($result['errors'] as $msg) {
                        $errors[] = ['row' => $rowNumber, 'message' => $msg];
                    }

                    continue;
                }

                $data = $result['data'];

                try {
                    if ($entity === self::ENTITY_CLIENTS) {
                        $emailKey = isset($data['email']) ? strtolower((string) $data['email']) : null;
                        if ($emailKey !== null && $emailKey !== '' && isset($existingEmails[$emailKey])) {
                            $failed++;
                            $errors[] = ['row' => $rowNumber, 'message' => 'Klient o tym adresie e-mail już istnieje: '.$emailKey];

                            continue;
                        }

                        $client = new Client;
                        $client->fill($this->onlyFillable($client, $data));
                        if (empty($client->type)) {
                            $client->type = 'individual';
                        }
                        $client->save();
                        $imported++;
                        if ($emailKey !== null && $emailKey !== '') {
                            $existingEmails[$emailKey] = true;
                        }
                    } else { // horses
                        $ownerId = null;
                        if (! empty($data['client_email'])) {
                            $email = strtolower((string) $data['client_email']);
                            if (! isset($clientByEmail[$email])) {
                                $clientByEmail[$email] = Client::query()
                                    ->whereRaw('LOWER(email) = ?', [$email])
                                    ->value('id');
                            }
                            $ownerId = $clientByEmail[$email];
                            if ($ownerId === null) {
                                $errors[] = ['row' => $rowNumber, 'message' => 'Nie znaleziono właściciela o e-mailu '.$email.' — koń zostanie zaimportowany bez właściciela.'];
                            }
                        }
                        unset($data['client_email']);

                        $name = (string) ($data['name'] ?? '');
                        $exists = Horse::query()
                            ->where('name', $name)
                            ->when($ownerId !== null, fn ($q) => $q->where('owner_client_id', $ownerId))
                            ->exists();
                        if ($exists) {
                            $failed++;
                            $errors[] = ['row' => $rowNumber, 'message' => 'Koń "'.$name.'" już istnieje (zduplikowany).'];

                            continue;
                        }

                        $horse = new Horse;
                        $horse->fill($this->onlyFillable($horse, $data));
                        if ($ownerId !== null) {
                            $horse->owner_client_id = $ownerId;
                        }
                        $horse->save();
                        $imported++;
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $errors[] = ['row' => $rowNumber, 'message' => 'Błąd zapisu: '.$e->getMessage()];
                }
            }
        });

        return [
            'imported' => $imported,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function onlyFillable(Model $model, array $data): array
    {
        $fillable = array_flip($model->getFillable());

        return array_intersect_key($data, $fillable);
    }

    private static function parseDate(mixed $raw): ?Carbon
    {
        if ($raw instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTime::createFromInterface($raw));
        }
        if (is_numeric($raw)) {
            // Excel serial date (days since 1900-01-01, with the famous leap-year bug).
            try {
                $unix = Date::excelToTimestamp((float) $raw);

                return Carbon::createFromTimestamp($unix)->startOfDay();
            } catch (\Throwable) {
                // fall through to string parsing
            }
        }
        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }
        // 4-digit year only ("2015") → Jan 1.
        if (preg_match('/^\d{4}$/', $value)) {
            return Carbon::createFromFormat('Y-m-d', $value.'-01-01')->startOfDay();
        }
        foreach (['Y-m-d', 'd.m.Y', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'm/d/Y'] as $fmt) {
            try {
                $d = Carbon::createFromFormat($fmt, $value);
                if ($d !== false) {
                    return $d->startOfDay();
                }
            } catch (\Throwable) {
                // try next
            }
        }
        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function normalize(string $s): string
    {
        $s = self::stripBom($s);
        $s = mb_strtolower(trim($s));

        return preg_replace('/\s+/u', ' ', $s) ?? $s;
    }

    private static function stripBom(string $s): string
    {
        if (str_starts_with($s, "\xEF\xBB\xBF")) {
            return substr($s, 3);
        }

        return $s;
    }

    private static function detectCsvEncoding(string $path): string
    {
        $head = (string) @file_get_contents($path, false, null, 0, 4096);
        if ($head === '') {
            return 'UTF-8';
        }
        if (str_starts_with($head, "\xEF\xBB\xBF")) {
            return 'UTF-8';
        }
        if (mb_check_encoding($head, 'UTF-8')) {
            return 'UTF-8';
        }

        // Polish Excel default for legacy CSV exports.
        return 'Windows-1250';
    }

    private static function detectCsvDelimiter(string $path): string
    {
        $head = (string) @file_get_contents($path, false, null, 0, 4096);
        $firstLine = strtok($head, "\r\n");
        if ($firstLine === false) {
            return ',';
        }
        $semis = substr_count($firstLine, ';');
        $commas = substr_count($firstLine, ',');

        return $semis > $commas ? ';' : ',';
    }
}
