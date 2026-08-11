<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Models\Department;
use Illuminate\Database\Seeder;

/**
 * Struktur departemen — divisi (induk) dan seksi (anak), menyerupai organisasi
 * Inalum: Operasi (peleburan/pencetakan/karbon), Pemeliharaan (mesin/listrik),
 * Keuangan & Korporat (keuangan/SDM/TI), serta Support & General Affairs (pemilik
 * proses ATK).
 *
 * SGA dan SIT dipertahankan kodenya karena muncul di wireframe blueprint. `parent_id`
 * membentuk hierarki divisi→seksi; `account_code` dipakai laporan Request by Account
 * (keputusan D3). Struktur final produksi tetap diisi lewat import data HR sebelum
 * go-live (risiko K1 roadmap) — updateOrCreate di sini aman ditimpa.
 */
class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        // Divisi (induk) — tanpa atasan departemen.
        $divisions = [
            ['code' => 'SGA', 'name' => 'Support & General Affairs', 'account_code' => '5400'],
            ['code' => 'OPS', 'name' => 'Divisi Operasi', 'account_code' => '5100'],
            ['code' => 'MTN', 'name' => 'Divisi Pemeliharaan', 'account_code' => '5200'],
            ['code' => 'FKT', 'name' => 'Divisi Keuangan & Korporat', 'account_code' => '5300'],
        ];

        /** @var array<string, int> $divisionIds */
        $divisionIds = [];

        foreach ($divisions as $division) {
            $divisionIds[$division['code']] = Department::updateOrCreate(
                ['code' => $division['code']],
                [
                    'name' => $division['name'],
                    'account_code' => $division['account_code'],
                    'parent_id' => null,
                    'is_active' => true,
                ],
            )->id;
        }

        // Seksi (anak) — masing-masing di bawah satu divisi.
        $sections = [
            ['code' => 'GA', 'name' => 'General Affairs', 'parent' => 'SGA', 'account_code' => '5401'],
            ['code' => 'PRC', 'name' => 'Pengadaan (Procurement)', 'parent' => 'SGA', 'account_code' => '5402'],
            ['code' => 'RED', 'name' => 'Peleburan (Reduction Plant)', 'parent' => 'OPS', 'account_code' => '5101'],
            ['code' => 'CAS', 'name' => 'Pencetakan (Casting Plant)', 'parent' => 'OPS', 'account_code' => '5102'],
            ['code' => 'CAR', 'name' => 'Carbon Plant', 'parent' => 'OPS', 'account_code' => '5103'],
            ['code' => 'MEC', 'name' => 'Pemeliharaan Mesin (Mechanical)', 'parent' => 'MTN', 'account_code' => '5201'],
            ['code' => 'ELE', 'name' => 'Listrik & Instrumentasi (Electrical)', 'parent' => 'MTN', 'account_code' => '5202'],
            ['code' => 'FIN', 'name' => 'Keuangan & Akuntansi', 'parent' => 'FKT', 'account_code' => '5301'],
            ['code' => 'HRD', 'name' => 'Sumber Daya Manusia', 'parent' => 'FKT', 'account_code' => '5302'],
            ['code' => 'SIT', 'name' => 'System & Information Technology', 'parent' => 'FKT', 'account_code' => '5303'],
        ];

        foreach ($sections as $section) {
            Department::updateOrCreate(
                ['code' => $section['code']],
                [
                    'name' => $section['name'],
                    'account_code' => $section['account_code'],
                    'parent_id' => $divisionIds[$section['parent']],
                    'is_active' => true,
                ],
            );
        }
    }
}
