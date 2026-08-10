<?php

declare(strict_types=1);

namespace App\Modules\Requisition\Enums;

enum RequestAction: string
{
    case Submit = 'submit';
    case ApproveL1 = 'approve_l1';
    case RejectL1 = 'reject_l1';
    case ApproveL2 = 'approve_l2';
    case RejectAll = 'reject_all';
    case ApproveL3 = 'approve_l3';
    case RejectL3 = 'reject_l3';
    case Revise = 'revise';
    case Handover = 'handover';
    case Cancel = 'cancel';

    public function label(): string
    {
        return match ($this) {
            self::Submit => 'Ajukan Request',
            self::ApproveL1 => 'Setujui (Pimpinan)',
            self::RejectL1 => 'Tolak (Pimpinan)',
            self::ApproveL2 => 'Simpan kuantitas (PIC Stationery)',
            self::RejectAll => 'Ditolak Seluruhnya',
            self::ApproveL3 => 'Setujui (Pimpinan SGA)',
            self::RejectL3 => 'Tolak (Pimpinan SGA)',
            self::Revise => 'Ajukan ulang',
            self::Handover => 'Diberikan',
            self::Cancel => 'Batalkan',
        };
    }
}
