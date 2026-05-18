<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mapping legacy → PWL.
 *
 * Listę dokumentów weryfikacyjnych transportera rozszerzyliśmy o PWL
 * (Przewóz Wewnątrzwspólnotowy Zwierząt Żywych). `insurance_ocp` było
 * jedyną legacy wartością typu „OC przewoźnika" — łączymy z nową
 * `carrier_liability_insurance` żeby istniejące konta nie miały duplikatów.
 *
 * Zachowujemy `animal_transport_cert` i `vehicle_registration` jako
 * deprecated cases enum'a — jeśli były wgrane jako dane historyczne,
 * nie tracimy ich. Master admin musi dodatkowo wgrać nowe PWL dokumenty
 * (świadectwo pojazdu PWL, świadectwo kompetencji kierowców) — legacy
 * NIE zaliczają się jako spełniony wymóg PWL.
 *
 * Patrz docs/TRANSPORT.md §13 — verification flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Konflikt: jeśli tenant ma JEDNOCZEŚNIE wpis 'insurance_ocp' i już
        // jakiś 'carrier_liability_insurance' (raczej niemożliwe — case dodawany
        // w tej samej PR), nie nadpisujemy nowego — usuwamy stary jako duplikat.
        $duplicates = DB::table('transporter_documents')
            ->where('document_type', 'insurance_ocp')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('transporter_documents as t2')
                    ->whereColumn('t2.document_type', DB::raw("'carrier_liability_insurance'"));
            })
            ->pluck('id');

        if ($duplicates->isNotEmpty()) {
            DB::table('transporter_documents')
                ->whereIn('id', $duplicates)
                ->delete();
        }

        // Mapowanie 1:1.
        DB::table('transporter_documents')
            ->where('document_type', 'insurance_ocp')
            ->update(['document_type' => 'carrier_liability_insurance']);
    }

    public function down(): void
    {
        DB::table('transporter_documents')
            ->where('document_type', 'carrier_liability_insurance')
            ->update(['document_type' => 'insurance_ocp']);
    }
};
