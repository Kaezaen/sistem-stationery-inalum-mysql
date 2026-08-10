<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Database\Factories\UomFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satuan ukur.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 */
class Uom extends Model
{
    /** @use HasFactory<UomFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['code', 'name'];

    /** @return HasMany<Item, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /** Model berada di dalam modul, sehingga factory ditunjuk manual. */
    protected static function newFactory(): UomFactory
    {
        return UomFactory::new();
    }
}
