<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Models\Uom;
use Illuminate\Database\Seeder;

/**
 * Satuan ukur baku. EACH diambil dari wireframe 3.11.2; sisanya satuan lazim ATK.
 */
class UomSeeder extends Seeder
{
    public function run(): void
    {
        $uoms = [
            ['code' => 'EACH', 'name' => 'Each'],
            ['code' => 'BOX', 'name' => 'Box'],
            ['code' => 'PACK', 'name' => 'Pack'],
            ['code' => 'ROLL', 'name' => 'Roll'],
            ['code' => 'SET', 'name' => 'Set'],
            ['code' => 'REAM', 'name' => 'Rim'],
            ['code' => 'BOTTLE', 'name' => 'Botol'],
            // Satuan tambahan dari katalog Inalum (Daftar Barang Stationaries).
            ['code' => 'KG', 'name' => 'Kilogram'],
            ['code' => 'UNIT', 'name' => 'Unit'],
            ['code' => 'BLOCK', 'name' => 'Block'],
        ];

        foreach ($uoms as $uom) {
            Uom::updateOrCreate(['code' => $uom['code']], ['name' => $uom['name']]);
        }
    }
}
