<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Exceptions;

use App\Shared\Exceptions\BusinessRuleException;

class InsufficientStockException extends BusinessRuleException
{
    public static function forItem(string $itemCode, int $requested, int $available): self
    {
        return new self(sprintf(
            'Stok item %s tidak mencukupi. Diminta %d, tersedia %d.',
            $itemCode,
            $requested,
            $available,
        ));
    }
}
