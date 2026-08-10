<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notifikasi in-app (Laravel Notifications, channel database) — §7 & fitur 6.
 *
 * Menyimpan notifikasi N1–N12 yang tampil di inbox pengguna. `data` memakai jsonb
 * agar isinya (judul, pesan, tautan) dapat di-query bila kelak diperlukan.
 *
 * read_at NULL = belum dibaca; indeks parsial idx_notif_unread mempercepat
 * penghitungan lencana "belum dibaca" yang dievaluasi tiap halaman.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            // notifiable_type varchar + notifiable_id bigint + indeks komposit.
            $table->morphs('notifiable');
            $table->jsonb('data');
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement(
            'CREATE INDEX idx_notif_unread ON notifications (notifiable_id) WHERE read_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
