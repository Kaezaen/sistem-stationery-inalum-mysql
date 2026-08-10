<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table): void {
            $table->id();
            $table->string('request_number', 20)->unique();   // REQ001
            $table->foreignId('requester_id')->constrained('users');

            /*
             * Departemen di-SNAPSHOT saat pengajuan, tidak dibaca ulang dari user.
             *
             * Pegawai dapat berpindah seksi; laporan "Request by Account" harus
             * tetap menghitung request pada seksi tempat request itu diajukan,
             * bukan seksi terbaru orangnya.
             */
            $table->foreignId('department_id')->constrained('departments');

            $table->date('request_date');
            $table->string('status', 30)->default('DRAFT');
            $table->smallInteger('current_approval_level')->default(0);
            $table->text('notes')->nullable();
            $table->smallInteger('revision_count')->default(0);
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();

            $table->index('requester_id');
            $table->index('department_id');
            $table->index('request_date');
            // Antrian approval pada layar Verify Request Items.
            $table->index(['status', 'request_date']);
        });

        DB::statement(
            "ALTER TABLE requests ADD CONSTRAINT chk_requests_status CHECK (status IN (
                'DRAFT',
                'PENDING_SUPERVISOR',  'REJECTED_SUPERVISOR',
                'PENDING_STATIONERY',  'REJECTED_STATIONERY',
                'PENDING_SGA',         'REJECTED_SGA',
                'READY_FOR_HANDOVER',
                'COMPLETED', 'CANCELLED'
            ))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
