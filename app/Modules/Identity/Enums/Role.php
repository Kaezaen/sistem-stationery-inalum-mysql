<?php

declare(strict_types=1);

namespace App\Modules\Identity\Enums;

/**
 * Enam role sistem — §7.1 Analisis Requirement.
 */
enum Role: string
{
    case Requester = 'requester';
    case Supervisor = 'supervisor';
    case PicStationery = 'pic_stationery';
    case SgaManager = 'sga_manager';
    case WarehouseOfficer = 'warehouse_officer';
    case Administrator = 'administrator';

    public function label(): string
    {
        return match ($this) {
            self::Requester => 'Requester',
            self::Supervisor => 'Pimpinan SIT',
            self::PicStationery => 'PIC Stationery',
            self::SgaManager => 'Pimpinan SGA',
            self::WarehouseOfficer => 'PIC Gudang',
            self::Administrator => 'Administrator',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Requester => 'Role dasar seluruh pegawai — mengajukan request ATK.',
            self::Supervisor => 'Approval Level 1 (Pimpinan SIT) untuk seluruh seksi.',
            self::PicStationery => 'Approval Level 2 (kuantitatif), kelola master item, verifikasi pembelian.',
            self::SgaManager => 'Approval Level 3 — keputusan final.',
            self::WarehouseOfficer => 'Input pembelian dan serah terima barang ke user.',
            self::Administrator => 'Kelola user, role, dan konfigurasi sistem.',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
