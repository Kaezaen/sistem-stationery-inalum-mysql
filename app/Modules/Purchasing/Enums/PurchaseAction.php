<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Enums;

enum PurchaseAction: string
{
    case Submit = 'submit';
    case Verify = 'verify';
    case Reject = 'reject';
    case Revise = 'revise';

    public function label(): string
    {
        return match ($this) {
            self::Submit => 'Ajukan verifikasi',
            self::Verify => 'Verifikasi',
            self::Reject => 'Tolak',
            self::Revise => 'Ajukan ulang',
        };
    }
}
