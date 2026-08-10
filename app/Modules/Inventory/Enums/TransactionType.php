<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

/**
 * Jenis pergerakan stok.
 *
 * Besaran (quantity) selalu positif; enum inilah yang menentukan arahnya.
 */
enum TransactionType: string
{
    /** Stok masuk — hanya dari verifikasi pembelian (§7 Architecture Blueprint). */
    case In = 'IN';

    /** Stok keluar — hanya dari serah terima barang ke user. */
    case Out = 'OUT';

    /** Koreksi — stock opname, saldo awal, pembetulan kesalahan. Wajib beralasan. */
    case Adjustment = 'ADJUSTMENT';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Masuk',
            self::Out => 'Keluar',
            self::Adjustment => 'Penyesuaian',
        };
    }

    /** Faktor arah terhadap saldo: +1 menambah, -1 mengurangi. */
    public function direction(): int
    {
        return match ($this) {
            self::In => 1,
            self::Out => -1,
            // Arah ADJUSTMENT ditentukan pemanggil, bukan oleh tipe.
            self::Adjustment => 0,
        };
    }

    public function requiresReason(): bool
    {
        return $this === self::Adjustment;
    }
}
