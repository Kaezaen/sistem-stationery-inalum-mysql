<?php

declare(strict_types=1);

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Reporting\Services\ReportExportService;
use App\Modules\Reporting\Support\ReportResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;

/*
| Export .xlsx (openspout) — pilihan "Hybrid" Fase 7.
|
| Diuji dua hal: berkas yang dihasilkan benar-benar .xlsx yang dapat dibaca ulang
| (header + baris sesuai urutan kolom), dan jalur unduhan digerbangi permission
| report.export — ?export=xlsx tidak boleh melangkahi kewenangan.
*/

uses(RefreshDatabase::class);

it('menghasilkan berkas xlsx yang dapat dibaca ulang dengan header dan baris', function (): void {
    $result = new ReportResult(
        key: 'uji-laporan',
        title: 'Uji Laporan',
        columns: [
            ['key' => 'item', 'label' => 'Item'],
            ['key' => 'qty', 'label' => 'Qty', 'align' => 'right', 'numeric' => true],
        ],
        rows: [
            ['item' => 'PENA', 'qty' => 10],
            ['item' => 'BUKU', 'qty' => 25],
        ],
        filterSchema: [],
        filters: [],
        meta: [],
    );

    $response = app(ReportExportService::class)->xlsx($result);
    $path = $response->getFile()->getPathname();

    $rows = [];
    $reader = new Reader;
    $reader->open($path);
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();
    @unlink($path);   // deleteFileAfterSend tidak terpicu di test — bersihkan manual

    expect($rows)->toHaveCount(3)                    // 1 header + 2 data
        ->and($rows[0])->toBe(['Item', 'Qty'])
        ->and($rows[1][0])->toBe('PENA')
        ->and((int) $rows[1][1])->toBe(10)
        ->and($rows[2][0])->toBe('BUKU');
});

it('mengunduh xlsx bagi yang berwenang export', function (): void {
    $this->seed(Database\Seeders\RolePermissionSeeder::class);

    $pic = User::factory()->create();
    $pic->assignRole([Role::Requester->value, Role::PicStationery->value]);

    $this->actingAs($pic)
        ->get('/reports/need-to-buy?export=xlsx')
        ->assertOk()
        ->assertDownload();
});

it('mengabaikan export dan tetap merender halaman bila tanpa permission export', function (): void {
    $this->seed(Database\Seeders\RolePermissionSeeder::class);

    // Pengguna diberi izin melihat laporan stok, TAPI bukan report.export.
    $viewer = User::factory()->create();
    $viewer->assignRole(Role::Requester->value);
    $viewer->givePermissionTo('report.stock.view');

    $this->actingAs($viewer)
        ->get('/reports/stock-by-month?export=xlsx')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Reports/Show'));
});
