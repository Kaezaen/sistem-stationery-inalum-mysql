<?php

declare(strict_types=1);

namespace App\Modules\Identity\Enums;

/**
 * Jabatan struktural.
 *
 * Blueprint menyebut approver sebagai "MS / VP" pada lane Pimpinan User dan
 * Pimpinan SGA (Gambar 2.1). Jabatan disimpan terpisah dari role karena keduanya
 * menjawab pertanyaan berbeda: jabatan = posisi organisasi, role = kewenangan
 * di dalam sistem. Seorang VP belum tentu memegang role approver.
 */
enum UserPosition: string
{
    case Staff = 'STAFF';
    case ManagerSection = 'MS';
    case VicePresident = 'VP';

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Staff',
            self::ManagerSection => 'Manager Section (MS)',
            self::VicePresident => 'Vice President (VP)',
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
