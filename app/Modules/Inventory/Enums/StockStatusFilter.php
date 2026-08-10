<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

/**
 * Nilai filter status pada layar Data Inventory.
 *
 * Terpisah dari Catalog\Enums\StockStatus: yang itu adalah HASIL evaluasi
 * terhadap satu item, yang ini adalah kriteria penyaringan pada query. Memakai
 * enum yang sama akan mengaburkan bahwa nilai kosong (= semua status) juga sah.
 */
enum StockStatusFilter: string
{
    case Over = 'over';
    case Under = 'under';
    case OnHand = 'on_hand';

    public function label(): string
    {
        return match ($this) {
            self::Over => 'Over Stock',
            self::Under => 'Under Stock',
            self::OnHand => 'Stock On Hand',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
