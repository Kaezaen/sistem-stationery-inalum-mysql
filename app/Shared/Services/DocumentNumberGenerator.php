<?php

declare(strict_types=1);

namespace App\Shared\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Penomoran dokumen berurutan tanpa duplikat dan tanpa lubang.
 *
 * Nomor diambil dengan mengunci baris sekuens (SELECT ... FOR UPDATE) di dalam
 * transaksi yang sama dengan penyimpanan dokumen. Bila transaksi di-rollback,
 * nomornya ikut kembali — berbeda dengan PostgreSQL SEQUENCE yang meninggalkan
 * lubang, sesuatu yang tidak diterima untuk dokumen approval.
 *
 * Dipakai modul Requisition untuk nomor REQ001 (Fase 5). Dokumen pembelian TIDAK
 * memakainya: nomornya diinput manual mengikuti nomor faktur pemasok, sesuai
 * wireframe 3.9.2.
 */
class DocumentNumberGenerator
{
    /**
     * Mengambil nomor berikutnya untuk satu jenis dokumen pada satu tahun.
     *
     * WAJIB dipanggil dari dalam DB transaction milik pemanggil, agar penguncian
     * baris sekuens bertahan sampai dokumennya benar-benar tersimpan.
     */
    public function next(string $documentType, string $prefix, int $padding = 3, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');

        return DB::transaction(function () use ($documentType, $prefix, $padding, $year): string {
            $row = DB::table('document_sequences')
                ->where('document_type', $documentType)
                ->where('period_year', $year)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                // Sisipkan baris sekuens bila tahun ini belum pernah dipakai.
                // Konflik dari proses lain yang menyisipkan bersamaan diserahkan
                // pada unique constraint, lalu dibaca ulang dengan penguncian.
                try {
                    DB::table('document_sequences')->insert([
                        'document_type' => $documentType,
                        'period_year' => $year,
                        'last_number' => 0,
                        'updated_at' => now(),
                    ]);
                } catch (Throwable) {
                    // Proses lain menang balapan; lanjut membaca barisnya.
                }

                $row = DB::table('document_sequences')
                    ->where('document_type', $documentType)
                    ->where('period_year', $year)
                    ->lockForUpdate()
                    ->first();
            }

            $next = (int) ($row->last_number ?? 0) + 1;

            DB::table('document_sequences')
                ->where('document_type', $documentType)
                ->where('period_year', $year)
                ->update(['last_number' => $next, 'updated_at' => now()]);

            return $prefix.str_pad((string) $next, $padding, '0', STR_PAD_LEFT);
        });
    }
}
