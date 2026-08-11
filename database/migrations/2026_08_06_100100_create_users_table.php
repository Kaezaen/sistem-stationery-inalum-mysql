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
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('employee_id', 30)->unique();   // NIP
            $table->string('username', 50)->unique();
            $table->string('name', 150);
            $table->string('email', 150)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            $table->foreignId('department_id')->constrained('departments');
            $table->string('position', 50)->nullable();    // STAFF | MS | VP

            /*
             * Penentu approver Level 1.
             *
             * Approval L1 diarahkan ke ATASAN LANGSUNG requester, bukan ke role
             * global — lihat §4 Analisis Requirement. Kolom inilah yang membuat
             * RequestPolicy::approveL1 dapat memastikan seorang pimpinan hanya
             * menyetujui request bawahannya sendiri (§5.2 Architecture Blueprint).
             */
            $table->foreignId('manager_id')->nullable()->constrained('users');

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('manager_id');
            $table->index('department_id');
        });

        // Mencegah user menjadi atasan dirinya sendiri — approval L1 pada request
        // miliknya tidak akan pernah bisa diputuskan orang lain. Siklus yang lebih
        // panjang (A->B->A) tidak dapat dicegah di sini dan divalidasi UserService.
        //
        // PostgreSQL memakai CHECK (manager_id IS DISTINCT FROM id). MySQL MENOLAK
        // CHECK yang mereferensikan kolom AUTO_INCREMENT (error 3818: "Check
        // constraint cannot refer to an auto-increment column"). Karena itu aturan
        // ditegakkan lewat trigger BEFORE INSERT/UPDATE — tetap jaring pengaman di
        // level database, sadar-NULL (user tanpa atasan tetap lolos).
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_users_not_own_manager_insert
            BEFORE INSERT ON users
            FOR EACH ROW
            BEGIN
                IF NEW.manager_id IS NOT NULL AND NEW.manager_id = NEW.id THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'User tidak boleh menjadi atasan dirinya sendiri';
                END IF;
            END
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_users_not_own_manager_update
            BEFORE UPDATE ON users
            FOR EACH ROW
            BEGIN
                IF NEW.manager_id IS NOT NULL AND NEW.manager_id = NEW.id THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'User tidak boleh menjadi atasan dirinya sendiri';
                END IF;
            END
            SQL);

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
