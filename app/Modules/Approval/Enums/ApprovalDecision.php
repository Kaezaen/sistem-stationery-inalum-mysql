<?php

declare(strict_types=1);

namespace App\Modules\Approval\Enums;

enum ApprovalDecision: string
{
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
        };
    }

    /** Penolakan wajib beralasan — ditegakkan juga oleh constraint database. */
    public function requiresReason(): bool
    {
        return $this === self::Rejected;
    }
}
