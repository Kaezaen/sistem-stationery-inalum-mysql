<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Support;

/**
 * Hasil satu laporan dalam bentuk tabular yang seragam.
 *
 * Kedelapan laporan (R1–R8) menghasilkan bentuk yang sama — daftar kolom + daftar
 * baris — sehingga satu halaman React, satu jalur export .xlsx, dan satu tampilan
 * cetak PDF dapat melayani semuanya tanpa duplikasi. Bentuk bespoke (grid pivot,
 * chart) disisakan untuk dashboard, bukan laporan tabular.
 *
 * `columns` mendefinisikan urutan dan label header; `rows` memakai kunci kolom.
 * `filterSchema` memberi tahu layar kontrol filter mana yang perlu dirender.
 */
final class ReportResult
{
    /**
     * @param  list<array{key: string, label: string, align?: string, numeric?: bool, format?: string}>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filterSchema
     * @param  array<string, mixed>  $filters
     * @param  list<array{label: string, value: int|string}>  $meta
     */
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly array $columns,
        public readonly array $rows,
        public readonly array $filterSchema,
        public readonly array $filters,
        public readonly array $meta = [],
        public readonly ?string $subtitle = null,
    ) {}

    /**
     * Header kolom sejajar dengan urutan `columns` — dipakai baris pertama .xlsx/PDF.
     *
     * @return list<string>
     */
    public function headerLabels(): array
    {
        return array_map(static fn (array $c): string => $c['label'], $this->columns);
    }

    /**
     * Nilai satu baris dalam urutan kolom — dipakai penulisan .xlsx/PDF.
     *
     * @param  array<string, mixed>  $row
     * @return list<mixed>
     */
    public function orderedValues(array $row): array
    {
        return array_map(static fn (array $c): mixed => $row[$c['key']] ?? null, $this->columns);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'columns' => $this->columns,
            'rows' => $this->rows,
            'filterSchema' => $this->filterSchema,
            'filters' => $this->filters,
            'meta' => $this->meta,
        ];
    }
}
