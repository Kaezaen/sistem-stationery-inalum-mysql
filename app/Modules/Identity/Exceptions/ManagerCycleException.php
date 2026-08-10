<?php

declare(strict_types=1);

namespace App\Modules\Identity\Exceptions;

use App\Shared\Exceptions\BusinessRuleException;

/**
 * Dilempar bila penetapan atasan akan membentuk siklus (A -> B -> A).
 *
 * Siklus pada manager_id membuat rantai approval tidak pernah mencapai puncak dan
 * berpotensi memblokir seluruh alur request. Ini risiko K1 pada roadmap.
 */
class ManagerCycleException extends BusinessRuleException
{
    /** @param list<string> $chain Nama user pada rantai yang membentuk siklus. */
    public static function forChain(array $chain): self
    {
        return new self(sprintf(
            'Penetapan atasan ini membentuk siklus: %s. Rantai atasan harus berakhir pada pucuk organisasi.',
            implode(' → ', $chain),
        ));
    }
}
