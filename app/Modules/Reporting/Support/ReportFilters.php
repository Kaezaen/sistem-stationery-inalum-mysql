<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Throwable;

/**
 * Filter lintas laporan (§10.1 Analisis Requirement).
 *
 * Menampung seluruh dimensi filter yang mungkin dipakai laporan mana pun —
 * periode, kategori, departemen, pencarian item. Tiap Query memakai bagian yang
 * relevan saja; filterSchema pada ReportResult yang menentukan kontrol mana yang
 * ditampilkan di layar.
 *
 * Laporan berbasis TANGGAL TRANSAKSI, bukan created_at (§10.1). Untuk stok itu
 * berarti periode snapshot; untuk pembelian purchase_date; untuk request
 * request_date.
 */
final class ReportFilters
{
    public function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly string $from,
        public readonly string $until,
        public readonly ?int $categoryId,
        public readonly ?int $departmentId,
        public readonly string $search,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $now = CarbonImmutable::now();

        $year = $request->integer('year') ?: $now->year;
        $month = $request->integer('month') ?: $now->month;
        $month = max(1, min(12, $month));

        $from = self::parseDate($request->query('from'), $now->startOfMonth());
        $until = self::parseDate($request->query('until'), $now->endOfMonth());

        return new self(
            year: $year,
            month: $month,
            from: $from,
            until: $until,
            categoryId: $request->integer('category_id') ?: null,
            departmentId: $request->integer('department_id') ?: null,
            search: trim((string) $request->string('search')),
        );
    }

    /**
     * Nilai filter yang dikembalikan ke layar agar kontrol tetap terisi.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'year' => $this->year,
            'month' => $this->month,
            'from' => $this->from,
            'until' => $this->until,
            'category_id' => $this->categoryId,
            'department_id' => $this->departmentId,
            'search' => $this->search,
        ];
    }

    /**
     * Mem-parse tanggal 'Y-m-d' dengan aman; nilai tak sah jatuh ke default.
     *
     * date() bawaan Request melempar pada format tak dikenal — di sini input
     * pengguna divalidasi manual agar filter yang salah ketik tidak menjatuhkan
     * seluruh halaman laporan.
     */
    private static function parseDate(mixed $value, CarbonImmutable $default): string
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            try {
                $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $value);
            } catch (Throwable) {
                $parsed = null;
            }

            if ($parsed !== null) {
                return $parsed->toDateString();
            }
        }

        return $default->toDateString();
    }
}
