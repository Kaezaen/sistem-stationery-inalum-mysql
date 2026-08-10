<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak audit teknis — §8.2 Analisis Requirement.
 *
 * Berbeda dari tabel `approvals` (jejak audit BISNIS yang tampil ke pengguna),
 * audit_logs merekam perubahan data granular untuk investigasi/kepatuhan dan
 * hanya diakses Administrator.
 *
 * Bersifat append-only: hanya created_at, tanpa updated_at — baris tidak pernah
 * diubah setelah dibuat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            // Nullable: sebagian kejadian tidak terikat entitas (mis. login gagal
            // dengan username yang tidak dikenal).
            $table->string('auditable_type', 100)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('event', 50); // created | updated | deleted | login | login_failed | ...
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id'], 'idx_audit_auditable');
            $table->index('user_id', 'idx_audit_user');
            $table->index('created_at', 'idx_audit_created');
        });

        // ip_address memakai tipe inet PostgreSQL (validasi format IP di database).
        // Ditambahkan lewat statement karena Schema builder tidak memetakan inet.
        DB::statement('ALTER TABLE audit_logs ADD COLUMN ip_address inet');
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
