<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel standard `notifications` table — wymagana przez database
 * notification channel. Używana przez Faza 6 PR 6.1 Owner notifications
 * hub (NewMessageForOwner, NewInvoiceForOwner, VetVisitRecordedForOwner)
 * + Faza 6 PR 6.2 LastOwnerActivityWidget.
 *
 * Schema zgodna z `php artisan notifications:table` output — standardowe
 * pola Laravel'a + indeks na notifiable_type + notifiable_id (potrzebny
 * dla User::notifications() relation HasMany lookup'u).
 *
 * Tabela na central DB — User mieszka w central więc notifications też.
 *
 * Idempotent: hasTable check przed create.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications')) {
            return;
        }

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
