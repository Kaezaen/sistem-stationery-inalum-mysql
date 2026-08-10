<?php

declare(strict_types=1);

namespace App\Modules\Approval\Contracts;

/**
 * Kontrak dokumen yang melewati approval.
 *
 * Engine approval bekerja HANYA terhadap kontrak ini — ia tidak boleh mengenal
 * Request maupun Purchase secara konkret (§2.2 aturan 4). Itulah yang membuat
 * satu engine dapat melayani dua alur yang sangat berbeda kompleksitasnya.
 *
 * Kontrak sengaja dijaga seminimal mungkin. Relasi approvals() TIDAK ikut
 * disyaratkan karena ApprovalService tidak pernah memanggilnya — ia menanyakan
 * langsung ke tabel approvals berdasarkan tipe dan id dokumen. Mensyaratkan
 * relasi hanya akan memaksa setiap implementasi menyamakan tipe generik Eloquent
 * tanpa manfaat apa pun.
 */
interface Approvable
{
    /** Level approval yang sedang menunggu keputusan. */
    public function currentApprovalLevel(): int;

    /**
     * Data per baris yang ikut disimpan sebagai bukti keputusan.
     *
     * @return array<string, mixed>|null
     */
    public function approvalSnapshot(): ?array;
}
