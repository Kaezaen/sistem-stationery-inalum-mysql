<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Uji Batas Modul
|--------------------------------------------------------------------------
|
| Menegakkan aturan ketergantungan §2.2 Architecture Blueprint secara otomatis.
|
| ADR-02 memutuskan batas modul dijaga oleh konvensi, bukan tooling. Berkas ini
| adalah penutup kelemahan keputusan tersebut: pelanggaran gagal di CI, bukan
| baru ketahuan saat review. Dibuat sejak Sprint 0 — menambahkannya belakangan
| berarti memperbaiki pelanggaran yang sudah terlanjur menyebar.
|
| Aturan yang menunggu modulnya ada diberi tanda TODO beserta fasenya.
|
*/

/** Seluruh modul bisnis. */
const MODULES = [
    'Identity', 'Catalog', 'Requisition', 'Approval', 'Fulfillment',
    'Purchasing', 'Inventory', 'Notification', 'Reporting', 'Audit',
];

/*
|--------------------------------------------------------------------------
| Aturan 1 — Arah ketergantungan hanya ke bawah
|--------------------------------------------------------------------------
*/
arch('Shared tidak boleh bergantung pada modul bisnis')
    ->expect('App\Shared')
    ->not->toUse(array_map(
        static fn (string $m): string => "App\\Modules\\{$m}",
        MODULES,
    ));

/*
|--------------------------------------------------------------------------
| Aturan 4 — Approval tidak mengenal Request/Purchase secara konkret
|--------------------------------------------------------------------------
| Engine approval bekerja pada kontrak Approvable agar dapat dipakai ulang
| Requisition maupun Purchasing tanpa perubahan.
*/
arch('Approval tidak boleh bergantung pada Requisition atau Purchasing')
    ->expect('App\Modules\Approval')
    ->not->toUse([
        'App\Modules\Requisition',
        'App\Modules\Purchasing',
    ]);

/*
|--------------------------------------------------------------------------
| Konvensi kode
|--------------------------------------------------------------------------
*/
arch('seluruh berkas memakai strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('tidak ada sisa debugging')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();

/*
|--------------------------------------------------------------------------
| Konvensi penamaan — berbasis pemindaian folder
|--------------------------------------------------------------------------
| Ditulis sebagai pemindaian direktori, bukan daftar namespace, agar modul baru
| otomatis ikut terperiksa tanpa menyunting berkas ini.
*/
it('menamai seluruh Controller dengan akhiran Controller', function (): void {
    $offenders = [];

    foreach (glob(base_path('app/Modules/*/Http/Controllers/*.php')) ?: [] as $file) {
        if (! str_ends_with(basename($file), 'Controller.php')) {
            $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
        }
    }

    expect($offenders)->toBe([]);
});

it('menamai seluruh Service dengan akhiran Service', function (): void {
    $offenders = [];

    foreach (glob(base_path('app/Modules/*/Services/*.php')) ?: [] as $file) {
        if (! str_ends_with(basename($file), 'Service.php')) {
            $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
        }
    }

    expect($offenders)->toBe([]);
});

it('menunjuk factory secara eksplisit pada model yang memakai HasFactory', function (): void {
    // Resolusi otomatis Laravel mengasumsikan model berada di App\Models. Karena
    // arsitektur ini menempatkan model di dalam modul, Laravel akan mencari
    // Database\Factories\Modules\<Modul>\Models\<X>Factory yang tidak pernah ada.
    // Gejalanya baru muncul saat test dijalankan — penjaga ini memunculkannya lebih awal.
    $offenders = [];

    foreach (glob(base_path('app/Modules/*/Models/*.php')) ?: [] as $file) {
        $source = (string) file_get_contents($file);

        if (! str_contains($source, 'HasFactory')) {
            continue;
        }

        if (! str_contains($source, 'function newFactory')) {
            $offenders[] = basename($file, '.php');
        }
    }

    expect($offenders)->toBe([], 'Model memakai HasFactory tanpa newFactory()');
});

it('memberi setiap modul sebuah ServiceProvider yang terdaftar', function (): void {
    $registered = require base_path('bootstrap/providers.php');

    $missingClass = [];
    $notRegistered = [];

    foreach (MODULES as $module) {
        $class = "App\\Modules\\{$module}\\{$module}ServiceProvider";

        if (! class_exists($class)) {
            $missingClass[] = $module;

            continue;
        }

        if (! in_array($class, $registered, true)) {
            $notRegistered[] = $module;
        }
    }

    expect($missingClass)->toBe([], 'Modul tanpa ServiceProvider');
    expect($notRegistered)->toBe([], 'ServiceProvider belum terdaftar di bootstrap/providers.php');
});

/*
|--------------------------------------------------------------------------
| Aturan 3 — Inventory adalah SATU-SATUNYA penulis stok
|--------------------------------------------------------------------------
| Aturan paling penting dalam sistem (ADR-08 & §8.1). Ledger hanya boleh
| disentuh dari dalam modul Inventory; modul lain memanggil StockService.
*/
arch('ledger hanya boleh dipakai di dalam modul Inventory')
    ->expect('App\Modules\Inventory\Models\InventoryTransaction')
    // Factory berada di Database\Factories mengikuti konvensi Laravel, sehingga
    // ia sah berada di luar namespace modul.
    ->toOnlyBeUsedIn(['App\Modules\Inventory', 'Database\Factories']);

it('tidak menulis kolom saldo stok di luar modul Inventory', function (): void {
    // Pemindaian teks, bukan analisis tipe: penulisan stock_quantity dapat
    // dilakukan lewat query builder tanpa menyentuh model mana pun, sehingga
    // aturan berbasis namespace tidak akan menangkapnya.
    $offenders = [];

    $files = array_merge(
        glob(base_path('app/Modules/*/Services/*.php')) ?: [],
        glob(base_path('app/Modules/*/Http/Controllers/*.php')) ?: [],
        glob(base_path('app/Modules/*/Models/*.php')) ?: [],
    );

    // Pola PENULISAN saja. Membaca saldo untuk ditampilkan tetap boleh, begitu
    // pula menyebut nama kolom pada casts — keduanya tidak mengubah data.
    $writePatterns = [
        // ->update([... 'stock_quantity' => ...])
        '/->(update|insert|updateOrCreate|forceFill)\s*\(\s*\[[^\]]*[\'"](stock_quantity|reserved_quantity)[\'"]\s*=>/s',
        // $item->stock_quantity = ...
        '/\$\w+->(stock_quantity|reserved_quantity)\s*=(?!=)/',
    ];

    foreach ($files as $file) {
        // glob() mengembalikan garis miring maju bahkan di Windows, sehingga
        // pemisah jalur dinormalkan sebelum dicocokkan.
        $normalized = str_replace('\\', '/', $file);

        if (str_contains($normalized, '/Modules/Inventory/')) {
            continue;
        }

        $source = (string) file_get_contents($file);

        foreach ($writePatterns as $pattern) {
            if (preg_match($pattern, $source) === 1) {
                $offenders[] = str_replace(str_replace('\\', '/', base_path()).'/', '', $normalized);

                break;
            }
        }
    }

    expect($offenders)->toBe([], 'Kolom saldo stok hanya boleh ditulis StockService');
});

/*
|--------------------------------------------------------------------------
| TODO — aturan yang menunggu modulnya diimplementasikan
|--------------------------------------------------------------------------
|
| Fase 5 (Requisition) — Controller tidak boleh menyentuh Model modul lain
| secara langsung; komunikasi lintas modul lewat Service publik (Aturan 2).
|
*/
