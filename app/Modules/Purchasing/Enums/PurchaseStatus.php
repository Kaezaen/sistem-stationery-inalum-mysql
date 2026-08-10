<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Enums;

/**
 * Status dokumen pembelian — §7 Architecture Blueprint.
 */
enum PurchaseStatus: string
{
    case Draft = 'DRAFT';
    case PendingVerification = 'PENDING_VERIFICATION';
    case Verified = 'VERIFIED';
    case Rejected = 'REJECTED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingVerification => 'Pending Approval',
            self::Verified => 'Diverifikasi',
            self::Rejected => 'Ditolak',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::PendingVerification => 'warning',
            self::Verified => 'success',
            self::Rejected => 'danger',
        };
    }

    /** Dokumen masih dapat disunting pembuatnya. */
    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Rejected;
    }

    /** Titik satu-satunya di mana stok bertambah. */
    public function hasAffectedStock(): bool
    {
        return $this === self::Verified;
    }
}
