<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zanonimizowana publiczna wersja dokumentu weryfikacyjnego. Master admin
 * (po sprawdzeniu oryginału) opcjonalnie uploaduje wersję bez PII — będzie
 * wyświetlana na publicznym profilu transportera (`/t/{slug}`) jako
 * potwierdzenie jakości obsługi (social proof obok opinii klientów).
 *
 * Reguły:
 *   - `public_file_path` NULL → dokument nie jest publikowany (default)
 *   - publikacja w UI tylko gdy `status=verified` AND `public_file_path IS NOT NULL`
 *   - Tylko master admin pisze do tych kolumn (nigdy transporter)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transporter_documents', function (Blueprint $table) {
            $table->string('public_file_path', 255)->nullable()->after('expiry_notified_at');
            $table->integer('public_file_size')->nullable()->after('public_file_path');
            $table->string('public_file_mime', 100)->nullable()->after('public_file_size');
            $table->timestamp('public_uploaded_at')->nullable()->after('public_file_mime');
            $table->string('public_uploaded_by_user_id', 26)->nullable()->after('public_uploaded_at');
        });
    }

    public function down(): void
    {
        Schema::table('transporter_documents', function (Blueprint $table) {
            $table->dropColumn([
                'public_file_path',
                'public_file_size',
                'public_file_mime',
                'public_uploaded_at',
                'public_uploaded_by_user_id',
            ]);
        });
    }
};
