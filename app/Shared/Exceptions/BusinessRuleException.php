<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Induk seluruh pelanggaran aturan bisnis.
 *
 * Dibedakan dari exception teknis agar dapat diterjemahkan menjadi pesan yang
 * dapat dibaca pengguna, bukan halaman 500. Service melempar exception ini;
 * Controller membiarkannya naik.
 */
class BusinessRuleException extends RuntimeException
{
    /**
     * Ubah menjadi ValidationException agar Inertia mengembalikan pengguna ke
     * form dengan pesan menempel pada field terkait.
     */
    public function toValidationException(string $field = 'error'): ValidationException
    {
        return ValidationException::withMessages([
            $field => $this->getMessage(),
        ]);
    }
}
