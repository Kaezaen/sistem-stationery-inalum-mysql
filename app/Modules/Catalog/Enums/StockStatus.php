<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

/**
 * Posisi stok terhadap batas min/max.
 *
 * Aturan di-reverse-engineer dari contoh angka pada wireframe 3.11.2:
 *   stok 15, min 5, max 10 -> Over Stock
 *   stok  3, min 5, max 10 -> Under Stock
 *   stok  7, min 5, max 10 -> Stock On Hand
 *
 * SENGAJA TIDAK disimpan sebagai kolom: nilainya turunan murni dari
 * stock_quantity, min_stock, dan max_stock. Menyimpannya akan menimbulkan
 * risiko data basi setiap kali stok bergerak.
 */
enum StockStatus: string
{
    case OverStock = 'OVER_STOCK';
    case UnderStock = 'UNDER_STOCK';
    case StockOnHand = 'STOCK_ON_HAND';

    public static function evaluate(int $stock, int $minStock, int $maxStock): self
    {
        if ($stock > $maxStock) {
            return self::OverStock;
        }

        if ($stock < $minStock) {
            return self::UnderStock;
        }

        return self::StockOnHand;
    }

    public function label(): string
    {
        return match ($this) {
            self::OverStock => 'Over Stock',
            self::UnderStock => 'Under Stock',
            self::StockOnHand => 'Stock On Hand',
        };
    }

    /** Perlu dibeli bila stok berada di bawah batas minimum. */
    public function needsRestock(): bool
    {
        return $this === self::UnderStock;
    }
}
