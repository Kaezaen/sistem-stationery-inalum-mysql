<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menutup ketergantungan melingkar antara departments dan users.
 *
 * departments.head_user_id -> users.id, sedangkan users.department_id -> departments.id.
 * Keduanya tidak bisa dibuat dalam satu migration, sehingga constraint arah
 * departments->users ditambahkan setelah kedua tabel ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->foreign('head_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->dropForeign(['head_user_id']);
        });
    }
};
