<?php

declare(strict_types=1);

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;

/*
| Matriks role-permission adalah kontrak keamanan sistem. Test ini menjaga agar
| perubahan tidak sengaja pada Permission::matrix() langsung terlihat — termasuk
| pelanggaran pemisahan tugas yang tidak akan tertangkap oleh test fungsional.
|
| Tidak menyentuh database: seluruhnya memeriksa definisi di kode.
*/

it('mendefinisikan permission untuk keenam role', function (): void {
    $matrix = Permission::matrix();

    foreach (Role::cases() as $role) {
        expect($matrix)->toHaveKey($role->value);
    }

    expect($matrix)->toHaveCount(count(Role::cases()));
});

it('hanya memuat permission yang benar-benar terdaftar', function (): void {
    $valid = Permission::values();
    $unknown = [];

    foreach (Permission::matrix() as $role => $permissions) {
        foreach ($permissions as $permission) {
            if (! in_array($permission, $valid, true)) {
                $unknown[] = "{$role}: {$permission}";
            }
        }
    }

    expect($unknown)->toBe([]);
});

it('memberi administrator seluruh permission', function (): void {
    $admin = Permission::matrix()[Role::Administrator->value];

    expect($admin)->toEqualCanonicalizing(Permission::values());
});

it('memisahkan ketiga level approval ke role yang berbeda', function (): void {
    // Pemisahan tugas: tidak boleh ada satu role memegang lebih dari satu level
    // approval request. Bila dilanggar, satu orang dapat meloloskan request
    // melewati beberapa gerbang sekaligus tanpa pengawasan pihak lain.
    $levels = [
        Permission::RequestApproveL1->value,
        Permission::RequestApproveL2->value,
        Permission::RequestApproveL3->value,
    ];

    foreach (Permission::matrix() as $role => $permissions) {
        if ($role === Role::Administrator->value) {
            continue;
        }

        $held = array_values(array_intersect($levels, $permissions));

        expect(count($held))->toBeLessThanOrEqual(
            1,
            "Role {$role} memegang lebih dari satu level approval: ".implode(', ', $held),
        );
    }
});

it('tidak memberi requester kewenangan approval apa pun', function (): void {
    $requester = Permission::matrix()[Role::Requester->value];

    expect($requester)
        ->not->toContain(Permission::RequestApproveL1->value)
        ->not->toContain(Permission::RequestApproveL2->value)
        ->not->toContain(Permission::RequestApproveL3->value)
        ->not->toContain(Permission::RequestHandover->value)
        ->not->toContain(Permission::UserManage->value);
});

it('memisahkan pembuat pembelian dari pemverifikasinya', function (): void {
    // PIC Gudang membuat pembelian, PIC Stationery memverifikasi. Bila satu role
    // memegang keduanya, stok dapat bertambah tanpa pemeriksaan pihak kedua —
    // persis kontrol yang dimaksud alur 3.9 dan 3.10 blueprint.
    foreach (Permission::matrix() as $role => $permissions) {
        if ($role === Role::Administrator->value) {
            continue;
        }

        $canCreate = in_array(Permission::PurchaseCreate->value, $permissions, true);
        $canVerify = in_array(Permission::PurchaseVerify->value, $permissions, true);

        expect($canCreate && $canVerify)->toBeFalse(
            "Role {$role} dapat membuat sekaligus memverifikasi pembelian",
        );
    }
});

it('memberi setiap role fungsional setidaknya satu permission', function (): void {
    foreach (Permission::matrix() as $role => $permissions) {
        expect($permissions)->not->toBeEmpty("Role {$role} tidak punya permission sama sekali");
    }
});
