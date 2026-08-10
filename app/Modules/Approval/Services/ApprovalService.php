<?php

declare(strict_types=1);

namespace App\Modules\Approval\Services;

use App\Modules\Approval\Contracts\Approvable;
use App\Modules\Approval\Enums\ApprovalDecision;
use App\Modules\Approval\Models\Approval;
use App\Modules\Identity\Models\User;
use App\Shared\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Model;

/**
 * Pencatat keputusan approval untuk dokumen apa pun.
 *
 * Sengaja TIDAK menyentuh status dokumen: perpindahan status adalah urusan
 * workflow masing-masing modul. Service ini hanya bertanggung jawab atas
 * riwayat keputusan, sehingga dapat dipakai ulang tanpa mengenal alur spesifik.
 */
class ApprovalService
{
    /**
     * Mencatat satu keputusan.
     *
     * @throws BusinessRuleException
     */
    public function record(
        Approvable&Model $document,
        User $approver,
        ApprovalDecision $decision,
        int $level,
        ?string $rejectionNotes = null,
    ): Approval {
        if ($decision->requiresReason() && trim((string) $rejectionNotes) === '') {
            throw new BusinessRuleException('Penolakan wajib disertai alasan.');
        }

        return Approval::create([
            'approvable_type' => $document::class,
            'approvable_id' => $document->getKey(),
            'approver_id' => $approver->id,
            'approval_level' => $level,
            // Peran diambil saat keputusan dibuat, bukan dibaca ulang nanti —
            // peran seseorang bisa berubah dan riwayat harus tetap akurat.
            'approver_role' => $approver->getRoleNames()->first() ?? 'unknown',
            'status' => $decision,
            'approval_date' => now(),
            'rejection_notes' => $decision->requiresReason() ? $rejectionNotes : null,
            'snapshot' => $document->approvalSnapshot(),
            'is_superseded' => false,
        ]);
    }

    /**
     * Menandai seluruh keputusan yang masih berlaku sebagai dianulir.
     *
     * Dipanggil saat dokumen direvisi dan diajukan ulang. Keputusan lama tidak
     * dihapus: auditor perlu melihat bahwa dokumen ini pernah ditolak, oleh
     * siapa, dan dengan alasan apa.
     */
    public function supersedeAll(Approvable&Model $document): int
    {
        return Approval::query()
            ->where('approvable_type', $document::class)
            ->where('approvable_id', $document->getKey())
            ->where('is_superseded', false)
            ->update(['is_superseded' => true, 'updated_at' => now()]);
    }

    /**
     * Riwayat keputusan terurut kronologis untuk ditampilkan sebagai timeline.
     *
     * @return \Illuminate\Support\Collection<int, Approval>
     */
    public function history(Approvable&Model $document): \Illuminate\Support\Collection
    {
        return Approval::query()
            ->with('approver:id,name')
            ->where('approvable_type', $document::class)
            ->where('approvable_id', $document->getKey())
            ->orderBy('approval_date')
            ->orderBy('id')
            ->get();
    }
}
