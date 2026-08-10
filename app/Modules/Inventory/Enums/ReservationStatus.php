<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum ReservationStatus: string
{
    /** Stok dikunci, menunggu serah terima. */
    case Held = 'HELD';

    /** Barang sudah diserahkan; reservasi berubah menjadi pengurangan stok nyata. */
    case Consumed = 'CONSUMED';

    /** Dilepas tanpa penyerahan — request ditolak SGA atau dibatalkan. */
    case Released = 'RELEASED';

    public function label(): string
    {
        return match ($this) {
            self::Held => 'Ditahan',
            self::Consumed => 'Diserahkan',
            self::Released => 'Dilepas',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Held;
    }
}
