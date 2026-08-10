<?php

declare(strict_types=1);

namespace App\Modules\Requisition\Enums;

/**
 * Sepuluh status request — §5.1 Analisis Requirement.
 *
 * Label mengikuti teks yang tampil pada wireframe blueprint, bukan terjemahan
 * bebas, agar pengguna melihat istilah yang sama dengan yang mereka setujui.
 */
enum RequestStatus: string
{
    case Draft = 'DRAFT';
    case PendingSupervisor = 'PENDING_SUPERVISOR';
    case RejectedSupervisor = 'REJECTED_SUPERVISOR';
    case PendingStationery = 'PENDING_STATIONERY';
    case RejectedStationery = 'REJECTED_STATIONERY';
    case PendingSga = 'PENDING_SGA';
    case RejectedSga = 'REJECTED_SGA';
    case ReadyForHandover = 'READY_FOR_HANDOVER';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingSupervisor => 'Pending Approval Pimpinan',
            self::RejectedSupervisor => 'Ditolak Pimpinan',
            self::PendingStationery => 'Pending Approval PIC Stationery',
            self::RejectedStationery => 'Ditolak PIC Stationery',
            self::PendingSga => 'Pending Approval Pimpinan SGA',
            self::RejectedSga => 'Ditolak Pimpinan SGA',
            self::ReadyForHandover => 'Pengambilan Item',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::PendingSupervisor, self::PendingStationery, self::PendingSga => 'warning',
            self::RejectedSupervisor, self::RejectedStationery, self::RejectedSga => 'danger',
            self::ReadyForHandover => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'neutral',
        };
    }

    /**
     * Level approval yang sedang menunggu keputusan.
     *
     * 0 berarti tidak sedang menunggu siapa pun.
     */
    public function pendingLevel(): int
    {
        return match ($this) {
            self::PendingSupervisor => 1,
            self::PendingStationery => 2,
            self::PendingSga => 3,
            default => 0,
        };
    }

    /** Status akhir — tidak ada aksi lanjutan. */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Cancelled, self::RejectedStationery => true,
            default => false,
        };
    }

    /**
     * Siapa yang bertanggung jawab merevisi setelah penolakan.
     *
     * Inti temuan blueprint: penolakan Pimpinan SGA kembali ke PIC STATIONERY,
     * bukan ke requester (Bab 3.7 dan General Workflow Gambar 2.1).
     */
    public function reviserRole(): ?string
    {
        return match ($this) {
            self::RejectedSupervisor => 'requester',
            self::RejectedSga => 'pic_stationery',
            default => null,
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            static fn (self $c): array => ['value' => $c->value, 'label' => $c->label()],
            self::cases(),
        );
    }
}
