<?php

declare(strict_types=1);

namespace App\Modules\Notification\Enums;

/**
 * Kode notifikasi N1–N12 (matriks §9.1 Analisis Requirement).
 *
 * Judul dan kanal dikunci di sini agar seluruh notifikasi memakai istilah yang
 * konsisten dan pilihan kanalnya (in-app / email) sesuai matriks — tidak tersebar
 * di banyak kelas.
 */
enum NotificationType: string
{
    case RequestSubmitted = 'N1';      // → Pimpinan User
    case RequestApprovedL1 = 'N2';     // → PIC Stationery
    case RequestRejectedL1 = 'N3';     // → Requester
    case RequestApprovedL2 = 'N4';     // → Pimpinan SGA
    case RequestRejectedL2 = 'N5';     // → Requester
    case RequestApprovedL3 = 'N6';     // → PIC Gudang + Requester
    case RequestRejectedL3 = 'N7';     // → PIC Stationery
    case RequestCompleted = 'N8';      // → Requester (in-app saja)
    case PurchaseSubmitted = 'N9';     // → PIC Stationery
    case PurchaseDecided = 'N10';      // → PIC Gudang
    case StockLow = 'N11';             // → PIC Stationery + PIC Gudang
    case ApprovalReminder = 'N12';     // → Approver (email)

    public function title(): string
    {
        return match ($this) {
            self::RequestSubmitted => 'Request Baru Menunggu Approval',
            self::RequestApprovedL1 => 'Request Menunggu Verifikasi PIC Stationery',
            self::RequestRejectedL1 => 'Request Ditolak Pimpinan',
            self::RequestApprovedL2 => 'Request Menunggu Approval Pimpinan SGA',
            self::RequestRejectedL2 => 'Request Ditolak PIC Stationery',
            self::RequestApprovedL3 => 'Request Disetujui — Siap Diserahkan',
            self::RequestRejectedL3 => 'Request Ditolak Pimpinan SGA',
            self::RequestCompleted => 'Barang Telah Diserahkan',
            self::PurchaseSubmitted => 'Pembelian Menunggu Verifikasi',
            self::PurchaseDecided => 'Status Pembelian Diperbarui',
            self::StockLow => 'Stok Mencapai Batas Minimum',
            self::ApprovalReminder => 'Pengingat Approval Tertunda',
        };
    }

    /**
     * Kanal pengiriman sesuai matriks §9.1.
     *
     * @return list<string>
     */
    public function channels(): array
    {
        return match ($this) {
            self::RequestCompleted => ['database'],   // N8 — in-app saja
            self::ApprovalReminder => ['mail'],       // N12 — email saja (SLA)
            default => ['database', 'mail'],
        };
    }
}
