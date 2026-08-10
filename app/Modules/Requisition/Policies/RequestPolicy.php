<?php

declare(strict_types=1);

namespace App\Modules\Requisition\Policies;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Modules\Requisition\Enums\RequestStatus;
use App\Modules\Requisition\Models\Request;

/**
 * Otorisasi kontekstual request — §5.2 Architecture Blueprint.
 *
 * Inilah lapis yang tidak dapat digantikan matriks permission. Permission hanya
 * menjawab "apakah role ini boleh menyetujui di level 1". Yang harus dijawab di
 * sini adalah: apakah user INI atasan langsung dari requester request INI, dan
 * apakah statusnya memang sedang menunggu level tersebut.
 *
 * Tanpa lapis ini, seorang Pimpinan dari seksi lain dapat menyetujui request
 * yang bukan wewenangnya.
 */
class RequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::RequestViewOwn->value);
    }

    /**
     * Siapa saja yang boleh melihat sebuah request.
     *
     * Berlapis dari yang paling sempit: pemiliknya, atasan langsungnya, lalu
     * pemegang kewenangan melihat seluruh request (PIC Stationery, SGA, Gudang).
     */
    public function view(User $user, Request $request): bool
    {
        if ($request->requester_id === $user->id) {
            return true;
        }

        if ($user->can(Permission::RequestViewAll->value)) {
            return true;
        }

        return $user->can(Permission::RequestViewSubordinate->value)
            && $request->requester !== null
            && $user->isDirectManagerOf($request->requester);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::RequestCreate->value);
    }

    /** Requester menyunting miliknya sendiri, selama masih draft. */
    public function update(User $user, Request $request): bool
    {
        return $user->can(Permission::RequestUpdate->value)
            && $request->requester_id === $user->id
            && $request->status === RequestStatus::Draft;
    }

    public function submit(User $user, Request $request): bool
    {
        return $user->can(Permission::RequestSubmit->value)
            && $request->requester_id === $user->id
            && $request->status === RequestStatus::Draft;
    }

    public function cancel(User $user, Request $request): bool
    {
        if (! $user->can(Permission::RequestCancel->value)) {
            return false;
        }

        // Setelah PIC Stationery menetapkan kuantitas, stok sudah dikunci dan
        // pembatalan sepihak oleh requester tidak lagi diizinkan.
        $cancellable = in_array($request->status, [
            RequestStatus::Draft,
            RequestStatus::PendingSupervisor,
            RequestStatus::RejectedSupervisor,
        ], true);

        return $cancellable && $request->requester_id === $user->id;
    }

    /*
    |--------------------------------------------------------------------------
    | Approval
    |--------------------------------------------------------------------------
    */

    /**
     * Level 1 — HANYA atasan langsung requester.
     *
     * Aturan paling penting di kelas ini. Blueprint mengarahkan approval L1 ke
     * atasan langsung (users.manager_id), bukan ke siapa pun yang kebetulan
     * memegang role supervisor.
     */
    public function approveL1(User $user, Request $request): bool
    {
        return $user->can(Permission::RequestApproveL1->value)
            && $request->status === RequestStatus::PendingSupervisor
            && $request->requester !== null
            && $user->isDirectManagerOf($request->requester);
    }

    public function approveL2(User $user, Request $request): bool
    {
        return $user->can(Permission::RequestApproveL2->value)
            && $request->status === RequestStatus::PendingStationery;
    }

    /**
     * Level 3 — berbasis role (keputusan D2), tanpa pembatasan unit.
     *
     * Bila D2 berubah menjadi SGA per unit, method ini harus mengikuti pola
     * approveL1 dengan pengecekan departemen.
     */
    public function approveL3(User $user, Request $request): bool
    {
        return $user->can(Permission::RequestApproveL3->value)
            && $request->status === RequestStatus::PendingSga;
    }

    /*
    |--------------------------------------------------------------------------
    | Revisi — dua jalur dengan aktor berbeda
    |--------------------------------------------------------------------------
    */

    /**
     * Ditolak Pimpinan User -> yang merevisi REQUESTER (Bab 3.6).
     * Ditolak Pimpinan SGA  -> yang merevisi PIC STATIONERY (Bab 3.7).
     *
     * Menyatukan keduanya dalam satu method menjaga agar aturan yang mudah
     * tertukar ini berada di satu tempat dan dapat dibaca berdampingan.
     */
    public function revise(User $user, Request $request): bool
    {
        return match ($request->status) {
            RequestStatus::RejectedSupervisor => $user->can(Permission::RequestUpdate->value)
                && $request->requester_id === $user->id,

            RequestStatus::RejectedSga => $user->can(Permission::RequestApproveL2->value),

            default => false,
        };
    }

    /** Serah terima oleh PIC Gudang — Fase 6. */
    public function handover(User $user, Request $request): bool
    {
        return $user->can(Permission::RequestHandover->value)
            && $request->status === RequestStatus::ReadyForHandover;
    }
}
