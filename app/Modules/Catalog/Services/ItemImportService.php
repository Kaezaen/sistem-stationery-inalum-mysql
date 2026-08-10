<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\Uom;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use SplFileObject;

/**
 * Import katalog awal dari berkas CSV.
 *
 * Katalog ATK yang sudah berjalan hampir pasti tersimpan dalam bentuk spreadsheet;
 * menginput ribuan item satu per satu tidak realistis dan akan menghambat UAT
 * (risiko K4 pada roadmap).
 *
 * Sengaja memakai CSV via SplFileObject, bukan paket pembaca Excel: format ini
 * dapat diekspor oleh Excel mana pun, tidak menambah dependensi berat, dan
 * menghindari eksekusi formula dari berkas yang diunggah pengguna.
 */
class ItemImportService
{
    /** Kolom yang wajib ada pada baris header. */
    public const REQUIRED_HEADERS = [
        'item_code', 'item_name', 'category', 'uom', 'min_stock', 'max_stock',
    ];

    /**
     * @return array{imported: int, updated: int, skipped: int, errors: list<string>}
     */
    public function import(UploadedFile $file, bool $updateExisting = false): array
    {
        $rows = $this->readCsv($file);

        if ($rows === []) {
            return ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ['Berkas kosong.']];
        }

        $header = array_map(
            static fn (string $h): string => strtolower(trim($h)),
            array_shift($rows),
        );

        $missing = array_diff(self::REQUIRED_HEADERS, $header);
        if ($missing !== []) {
            return [
                'imported' => 0, 'updated' => 0, 'skipped' => 0,
                'errors' => ['Kolom wajib tidak ditemukan: '.implode(', ', $missing)],
            ];
        }

        $categories = Category::pluck('id', 'code')->all();
        $categoriesByName = Category::pluck('id', 'name')->all();
        $uoms = Uom::pluck('id', 'code')->all();

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        // Satu transaksi untuk seluruh berkas: import separuh jalan akan
        // meninggalkan katalog dalam keadaan yang sulit dipulihkan.
        DB::transaction(function () use (
            $rows, $header, $categories, $categoriesByName, $uoms, $updateExisting,
            &$imported, &$updated, &$skipped, &$errors
        ): void {
            foreach ($rows as $index => $raw) {
                $lineNumber = $index + 2; // +1 header, +1 basis satu
                $row = $this->combine($header, $raw);

                if (trim($row['item_code'] ?? '') === '') {
                    $skipped++;

                    continue;
                }

                $categoryKey = trim((string) $row['category']);
                $categoryId = $categories[$categoryKey] ?? $categoriesByName[$categoryKey] ?? null;

                if ($categoryId === null) {
                    $errors[] = "Baris {$lineNumber}: kategori '{$categoryKey}' tidak dikenal.";
                    $skipped++;

                    continue;
                }

                $uomKey = strtoupper(trim((string) $row['uom']));
                $uomId = $uoms[$uomKey] ?? null;

                if ($uomId === null) {
                    $errors[] = "Baris {$lineNumber}: UoM '{$uomKey}' tidak dikenal.";
                    $skipped++;

                    continue;
                }

                $min = (int) $row['min_stock'];
                $max = (int) $row['max_stock'];

                if ($min > $max) {
                    $errors[] = "Baris {$lineNumber}: Min Stock ({$min}) melebihi Max Stock ({$max}).";
                    $skipped++;

                    continue;
                }

                $code = trim((string) $row['item_code']);
                $existing = Item::withTrashed()->where('item_code', $code)->first();

                $attributes = [
                    'item_name' => trim((string) $row['item_name']),
                    'description' => isset($row['description']) ? trim((string) $row['description']) : null,
                    'category_id' => $categoryId,
                    'uom_id' => $uomId,
                    'min_stock' => $min,
                    'max_stock' => $max,
                    'remark' => isset($row['remark']) ? trim((string) $row['remark']) : null,
                    'is_active' => true,
                ];

                if ($existing !== null) {
                    if (! $updateExisting) {
                        $skipped++;

                        continue;
                    }

                    $existing->restore();
                    $existing->update($attributes);
                    $updated++;

                    continue;
                }

                Item::create([...$attributes, 'item_code' => $code]);
                $imported++;
            }
        });

        return compact('imported', 'updated', 'skipped', 'errors');
    }

    /**
     * Menyelaraskan panjang baris dengan header lalu memetakannya.
     *
     * Panjang disamakan lebih dulu karena spreadsheet kerap memangkas kolom
     * kosong di ujung kanan, dan array_combine pada PHP 8 melempar ValueError
     * bila panjang keduanya berbeda.
     *
     * @param  list<string>  $header
     * @param  list<string>  $raw
     * @return array<string, string>
     */
    private function combine(array $header, array $raw): array
    {
        if (count($raw) < count($header)) {
            $raw = array_pad($raw, count($header), '');
        }

        if (count($raw) > count($header)) {
            $raw = array_slice($raw, 0, count($header));
        }

        return array_combine($header, $raw);
    }

    /**
     * @return list<list<string>>
     */
    private function readCsv(UploadedFile $file): array
    {
        $handle = new SplFileObject($file->getRealPath(), 'r');
        $handle->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        $rows = [];

        foreach ($handle as $line) {
            if (! is_array($line) || $line === [null]) {
                continue;
            }

            $rows[] = array_map(static fn (mixed $v): string => (string) $v, $line);
        }

        // Membuang BOM UTF-8 yang ditinggalkan Excel pada sel pertama.
        if (isset($rows[0][0])) {
            $rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $rows[0][0]) ?? $rows[0][0];
        }

        return $rows;
    }
}
