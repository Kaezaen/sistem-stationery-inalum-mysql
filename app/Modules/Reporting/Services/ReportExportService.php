<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Services;

use App\Modules\Reporting\Support\ReportResult;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Export laporan ke berkas .xlsx sesungguhnya (bukan CSV).
 *
 * Satu exporter melayani kedelapan laporan karena semuanya berbentuk tabular
 * seragam (ReportResult): baris pertama = label kolom, sisanya = nilai baris dalam
 * urutan kolom. Dipakai openspout — pustaka ramping yang mengalirkan .xlsx tanpa
 * kopling versi framework, sejalan dengan pilihan "Hybrid" untuk Fase 7.
 *
 * PDF TIDAK melewati sini: laporan dicetak lewat halaman siap-cetak di browser
 * (pola struk Fase 6), sehingga tidak ada dependensi PDF di server.
 */
class ReportExportService
{
    public function xlsx(ReportResult $result): BinaryFileResponse
    {
        // openspout menulis zip .xlsx ke path ini; deleteFileAfterSend membersihkannya.
        $path = tempnam(sys_get_temp_dir(), 'report_');

        if ($path === false) {
            abort(500, 'Gagal menyiapkan berkas ekspor.');
        }

        $writer = new Writer;
        $writer->openToFile($path);

        $writer->addRow(Row::fromValues($result->headerLabels()));

        foreach ($result->rows as $row) {
            $writer->addRow(Row::fromValues($result->orderedValues($row)));
        }

        $writer->close();

        $filename = sprintf('%s-%s.xlsx', $result->key, now()->format('Ymd-His'));

        return response()
            ->download($path, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }
}
