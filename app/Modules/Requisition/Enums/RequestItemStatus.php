<?php

declare(strict_types=1);

namespace App\Modules\Requisition\Enums;

/**
 * Status per baris — diperlukan karena approval L2 bersifat KUANTITATIF.
 *
 * Sebuah request dapat disetujui sebagian: sebagian barisnya penuh, sebagian
 * dikurangi, sebagian ditolak. Status dokumen saja tidak mampu menyatakan itu.
 */
enum RequestItemStatus: string
{
    case Requested = 'REQUESTED';
    case Approved = 'APPROVED';
    case PartiallyApproved = 'PARTIALLY_APPROVED';
    case Rejected = 'REJECTED';
    case Issued = 'ISSUED';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Diminta',
            self::Approved => 'Disetujui penuh',
            self::PartiallyApproved => 'Disetujui sebagian',
            self::Rejected => 'Ditolak',
            self::Issued => 'Diserahkan',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Requested => 'neutral',
            self::Approved, self::Issued => 'success',
            self::PartiallyApproved => 'warning',
            self::Rejected => 'danger',
        };
    }

    /** Menyimpulkan status baris dari kuantitas yang disetujui. */
    public static function fromQuantities(int $requested, int $approved): self
    {
        if ($approved <= 0) {
            return self::Rejected;
        }

        return $approved >= $requested ? self::Approved : self::PartiallyApproved;
    }
}
