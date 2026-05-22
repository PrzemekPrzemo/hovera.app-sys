<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dodaje dyskryminator `buyer_type` na FV: 'individual' (osoba fizyczna,
 * NIP opcjonalny / brak) lub 'company' (firma, NIP wymagany).
 *
 * Zgodnie z polskim prawem podatkowym FV dla osoby fizycznej nieprowadzącej
 * działalności gospodarczej może NIE zawierać NIP'u — wystarczy nazwa
 * (imie i nazwisko) + opcjonalnie adres. KSeF FA(3) obsługuje to przez
 * pominięcie pola NabywcaNIP i wpis `Brak` w danych identyfikacyjnych.
 *
 * Backfill istniejących rekordów: jeśli `buyer_nip` set → `company`,
 * else → `individual`. To realistic guess — przed dodaniem tej kolumny
 * NIP był jedynym sygnałem typu.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['invoices', 'transport_invoices'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->string('buyer_type', 16)->default('individual')->after('buyer_country');
            });

            // Backfill: company gdy NIP set, individual gdy null/empty.
            DB::table($table)
                ->whereNotNull('buyer_nip')
                ->where('buyer_nip', '!=', '')
                ->update(['buyer_type' => 'company']);
        }
    }

    public function down(): void
    {
        foreach (['invoices', 'transport_invoices'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('buyer_type');
            });
        }
    }
};
