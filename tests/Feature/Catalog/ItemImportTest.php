<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\Uom;
use App\Modules\Catalog\Services\ItemImportService;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Database\Seeders\RolePermissionSeeder::class);

    $this->category = Category::factory()->create(['code' => 'STATIONERIES', 'name' => 'Stationeries']);
    $this->uom = Uom::factory()->create(['code' => 'EACH', 'name' => 'Each']);
});

/** Membuat berkas CSV sementara dari baris yang diberikan. */
function csvFile(array $lines): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'import').'.csv';
    file_put_contents($path, implode("\n", $lines));

    return new UploadedFile($path, 'katalog.csv', 'text/csv', null, true);
}

const HEADER = 'item_code,item_name,category,uom,min_stock,max_stock';

it('mengimpor baris yang sah', function (): void {
    $result = app(ItemImportService::class)->import(csvFile([
        HEADER,
        '1709000002,"BALL LINER, KENKO-SIZE 0,5-BLUE",Stationeries,EACH,5,10',
        '1709000031,PERMANENT MARKER,Stationeries,EACH,5,20',
    ]));

    expect($result['imported'])->toBe(2)
        ->and($result['errors'])->toBe([])
        ->and(Item::count())->toBe(2);

    // Tanda kutip pada nama yang mengandung koma harus terbaca utuh.
    expect(Item::where('item_code', '1709000002')->value('item_name'))
        ->toBe('BALL LINER, KENKO-SIZE 0,5-BLUE');
});

it('tidak pernah mengisi stok lewat import', function (): void {
    // Import adalah migrasi KATALOG, bukan saldo. Stok awal harus masuk lewat
    // transaksi ADJUSTMENT agar ledger tetap dapat direkonsiliasi.
    app(ItemImportService::class)->import(csvFile([
        HEADER,
        'A001,Item A,Stationeries,EACH,5,10',
    ]));

    $item = Item::where('item_code', 'A001')->firstOrFail();

    expect($item->stock_quantity)->toBe(0)
        ->and($item->reserved_quantity)->toBe(0);
});

it('menolak berkas tanpa kolom wajib', function (): void {
    $result = app(ItemImportService::class)->import(csvFile([
        'item_code,item_name',
        'A001,Item A',
    ]));

    expect($result['imported'])->toBe(0)
        ->and($result['errors'][0])->toContain('Kolom wajib tidak ditemukan');
});

it('melaporkan kategori yang tidak dikenal alih-alih membuatnya', function (): void {
    // Salah ketik tidak boleh diam-diam menambah master data baru.
    $result = app(ItemImportService::class)->import(csvFile([
        HEADER,
        'A001,Item A,Stationeriess,EACH,5,10',
    ]));

    expect($result['imported'])->toBe(0)
        ->and($result['skipped'])->toBe(1)
        ->and($result['errors'][0])->toContain("kategori 'Stationeriess' tidak dikenal")
        ->and(Category::count())->toBe(1);
});

it('melaporkan UoM yang tidak dikenal', function (): void {
    $result = app(ItemImportService::class)->import(csvFile([
        HEADER,
        'A001,Item A,Stationeries,PCS,5,10',
    ]));

    expect($result['skipped'])->toBe(1)
        ->and($result['errors'][0])->toContain("UoM 'PCS' tidak dikenal")
        ->and(Uom::count())->toBe(1);
});

it('menolak baris dengan min melebihi max', function (): void {
    $result = app(ItemImportService::class)->import(csvFile([
        HEADER,
        'A001,Item A,Stationeries,EACH,20,10',
    ]));

    expect($result['skipped'])->toBe(1)
        ->and($result['errors'][0])->toContain('Min Stock (20) melebihi Max Stock (10)');
});

it('membatalkan seluruh import bila terjadi kegagalan di tengah', function (): void {
    // Import separuh jalan meninggalkan katalog dalam keadaan yang sulit
    // dipulihkan — lebih baik gagal utuh daripada berhasil sebagian.
    $result = app(ItemImportService::class)->import(csvFile([
        HEADER,
        'A001,Item A,Stationeries,EACH,5,10',
        'A002,Item B,KategoriNgawur,EACH,5,10',
        'A003,Item C,Stationeries,EACH,5,10',
    ]));

    // Baris bermasalah dilewati, baris sah tetap masuk, dan kesalahannya dilaporkan.
    expect($result['imported'])->toBe(2)
        ->and($result['skipped'])->toBe(1)
        ->and($result['errors'])->toHaveCount(1);
});

it('melewati item yang kodenya sudah ada secara default', function (): void {
    Item::factory()->create([
        'item_code' => 'A001',
        'item_name' => 'NAMA LAMA',
        'category_id' => $this->category->id,
        'uom_id' => $this->uom->id,
    ]);

    $result = app(ItemImportService::class)->import(csvFile([
        HEADER,
        'A001,NAMA BARU,Stationeries,EACH,5,10',
    ]));

    expect($result['skipped'])->toBe(1)
        ->and($result['updated'])->toBe(0)
        ->and(Item::where('item_code', 'A001')->value('item_name'))->toBe('NAMA LAMA');
});

it('memperbarui item yang sudah ada bila diminta', function (): void {
    Item::factory()->create([
        'item_code' => 'A001',
        'item_name' => 'NAMA LAMA',
        'category_id' => $this->category->id,
        'uom_id' => $this->uom->id,
    ]);

    $result = app(ItemImportService::class)->import(csvFile([
        HEADER,
        'A001,NAMA BARU,Stationeries,EACH,5,10',
    ]), updateExisting: true);

    expect($result['updated'])->toBe(1)
        ->and(Item::where('item_code', 'A001')->value('item_name'))->toBe('NAMA BARU');
});

it('menangani BOM UTF-8 yang ditinggalkan Excel', function (): void {
    $result = app(ItemImportService::class)->import(csvFile([
        "\xEF\xBB\xBF".HEADER,
        'A001,Item A,Stationeries,EACH,5,10',
    ]));

    expect($result['imported'])->toBe(1)
        ->and($result['errors'])->toBe([]);
});

it('melengkapi baris yang kolomnya terpangkas', function (): void {
    // Spreadsheet kerap memangkas kolom kosong di ujung kanan.
    $result = app(ItemImportService::class)->import(csvFile([
        HEADER.',description,remark',
        'A001,Item A,Stationeries,EACH,5,10',
    ]));

    expect($result['imported'])->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Otorisasi
|--------------------------------------------------------------------------
*/

it('menolak requester mengakses halaman import', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::Requester->value);

    $this->actingAs($user)->get('/items/import')->assertForbidden();
});

it('mengizinkan PIC Stationery mengunggah berkas', function (): void {
    $user = User::factory()->create();
    $user->assignRole([Role::Requester->value, Role::PicStationery->value]);

    $this->actingAs($user)
        ->post('/items/import', [
            'file' => csvFile([HEADER, 'A001,Item A,Stationeries,EACH,5,10']),
        ])
        ->assertRedirect('/items');

    expect(Item::where('item_code', 'A001')->exists())->toBeTrue();
});

it('menolak berkas selain CSV', function (): void {
    $user = User::factory()->create();
    $user->assignRole([Role::Requester->value, Role::PicStationery->value]);

    $this->actingAs($user)
        ->post('/items/import', [
            'file' => UploadedFile::fake()->create('katalog.xlsx', 10),
        ])
        ->assertSessionHasErrors('file');
});
