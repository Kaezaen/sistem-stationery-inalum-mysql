<?php

declare(strict_types=1);

namespace App\Shared\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

/**
 * Induk seluruh Controller modul.
 *
 * Laravel 12 tidak lagi memasang trait apa pun pada base controller-nya, sehingga
 * $this->authorize() tidak tersedia secara default. Trait dipasang di sini agar
 * setiap Controller modul dapat memanggilnya.
 *
 * Aturan tim: setiap action yang MENGUBAH state wajib memanggil authorize().
 * Pemeriksaan permission di sisi React hanya menyembunyikan tombol — bukan
 * kontrol keamanan (§5.2 Architecture Blueprint).
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    /**
     * Menegakkan permission untuk action yang tidak terikat pada satu model.
     *
     * Sebagian layar (mis. Data Inventory) tidak bekerja pada satu entitas
     * tertentu sehingga tidak punya Policy. Helper ini menjaga agar pemeriksaan
     * kewenangan tetap eksplisit di setiap action, bukan diserahkan pada
     * middleware yang mudah terlupa saat menambah route baru.
     *
     * Menerima string, BUKAN enum Permission: lapis Shared adalah fondasi dan
     * tidak boleh bergantung pada modul bisnis mana pun (§2.2 aturan 1).
     * Pemanggil meneruskan Permission::X->value.
     */
    protected function authorizePermission(Request $request, string $permission): void
    {
        abort_unless(
            $request->user()?->can($permission) ?? false,
            403,
        );
    }
}
