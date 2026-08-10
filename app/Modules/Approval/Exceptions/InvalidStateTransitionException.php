<?php

declare(strict_types=1);

namespace App\Modules\Approval\Exceptions;

use App\Shared\Exceptions\BusinessRuleException;

/**
 * Transisi status yang tidak sah.
 *
 * Menutup dua kelas kesalahan sekaligus: aksi yang memang tidak diizinkan dari
 * status saat ini, dan approval ganda akibat dua approver menekan tombol nyaris
 * bersamaan — yang kedua akan menemukan status sudah berpindah.
 */
class InvalidStateTransitionException extends BusinessRuleException
{
    public static function make(string $document, string $from, string $action): self
    {
        return new self(sprintf(
            '%s berstatus %s tidak dapat menjalankan aksi "%s". '
            .'Kemungkinan dokumen sudah diproses orang lain — muat ulang halaman.',
            $document,
            $from,
            $action,
        ));
    }
}
