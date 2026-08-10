<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\Uom;
use Illuminate\Database\Seeder;

/**
 * Katalog item Inalum — 236 barang dari "Daftar Barang Aplikasi Stationaries".
 *
 * Data dinormalkan ke database/data/stationery-items.csv (kode, nama, kategori,
 * satuan, min/max) agar seed ini reproducible dan mudah ditinjau. Tidak mengisi
 * stok: saldo awal masuk lewat transaksi ADJUSTMENT (stock:adjust) saat go-live,
 * bukan lewat seed katalog (aturan Fase 2).
 *
 * Idempoten (updateOrCreate atas item_code). Event dimatikan selama seed agar
 * pembuatan massal tidak membanjiri audit_logs.
 */
class StationeryItemSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/stationery-items.csv');

        if (! is_file($path)) {
            $this->command?->warn("Berkas katalog tidak ditemukan: {$path} — dilewati.");

            return;
        }

        /** @var array<string, int> $categories code => id */
        $categories = Category::query()->pluck('id', 'code')->all();
        /** @var array<string, int> $uoms code => id */
        $uoms = Uom::query()->pluck('id', 'code')->all();

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return;
        }

        fgetcsv($handle); // lewati header

        $written = 0;
        $missing = [];

        Item::withoutEvents(function () use ($handle, $categories, $uoms, &$written, &$missing): void {
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 6) {
                    continue;
                }

                [$code, $name, $catCode, $uomCode, $min, $max] = $row;
                $remark = $row[6] ?? '';

                $categoryId = $categories[$catCode] ?? null;
                $uomId = $uoms[$uomCode] ?? null;

                if ($categoryId === null || $uomId === null) {
                    $missing[] = $code;

                    continue;
                }

                Item::updateOrCreate(
                    ['item_code' => (string) $code],
                    [
                        'item_name' => (string) $name,
                        'category_id' => $categoryId,
                        'uom_id' => $uomId,
                        'min_stock' => (int) $min,
                        'max_stock' => (int) $max,
                        'remark' => trim((string) $remark) !== '' ? (string) $remark : null,
                        'is_active' => true,
                    ],
                );

                $written++;
            }
        });

        fclose($handle);

        $this->command?->info("Katalog stationery: {$written} item ter-seed.");

        if ($missing !== []) {
            $this->command?->warn('Kategori/UoM tidak dikenal untuk kode: '.implode(', ', $missing));
        }
    }
}
