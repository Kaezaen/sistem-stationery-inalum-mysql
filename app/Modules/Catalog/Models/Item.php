<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\StockStatus;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $item_code
 * @property string $item_name
 * @property string|null $description
 * @property int $category_id
 * @property int $uom_id
 * @property int $stock_quantity
 * @property int $reserved_quantity
 * @property int $min_stock
 * @property int $max_stock
 * @property string|null $remark
 * @property bool $is_active
 */
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory, SoftDeletes;

    /**
     * stock_quantity dan reserved_quantity SENGAJA tidak fillable.
     *
     * Keduanya hanya boleh berubah lewat StockService di modul Inventory
     * (ADR-08). Mengeluarkannya dari fillable membuat mass assignment dari
     * form manapun tidak dapat menyentuh saldo stok — bahkan jika ada kesalahan
     * validasi di lapisan Controller.
     *
     * @var list<string>
     */
    protected $fillable = [
        'item_code',
        'item_name',
        'description',
        'category_id',
        'uom_id',
        'min_stock',
        'max_stock',
        'remark',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'stock_quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'min_stock' => 'integer',
            'max_stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relasi
    |--------------------------------------------------------------------------
    */

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Uom, $this> */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Perilaku domain
    |--------------------------------------------------------------------------
    */

    /**
     * Jumlah yang benar-benar dapat dijanjikan ke request baru.
     *
     * Stok fisik dikurangi yang sudah dikunci untuk request yang disetujui
     * namun belum diserahkan (ADR-07). Tanpa pengurangan ini, dua request dapat
     * disetujui atas barang yang sama.
     */
    public function availableQuantity(): int
    {
        return max(0, $this->stock_quantity - $this->reserved_quantity);
    }

    public function stockStatus(): StockStatus
    {
        return StockStatus::evaluate($this->stock_quantity, $this->min_stock, $this->max_stock);
    }

    /** Usulan jumlah pembelian agar stok kembali ke batas maksimum. */
    public function suggestedPurchaseQuantity(): int
    {
        return max(0, $this->max_stock - $this->stock_quantity);
    }

    /*
    |--------------------------------------------------------------------------
    | Query scope
    |--------------------------------------------------------------------------
    */

    /** @param Builder<Item> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Pencarian berdasarkan kode atau nama.
     *
     * @param  Builder<Item>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        if ($term === '') {
            return;
        }

        $pattern = '%'.$term.'%';

        $query->where(function (Builder $sub) use ($pattern): void {
            $sub->where('item_name', 'like', $pattern)
                ->orWhere('item_code', 'like', $pattern);
        });
    }

    /** Item yang stoknya di bawah batas minimum — laporan Need to Buy (R8). */
    /** @param Builder<Item> $query */
    public function scopeNeedsRestock(Builder $query): void
    {
        $query->whereColumn('stock_quantity', '<', 'min_stock');
    }

    /** Model berada di dalam modul, sehingga factory ditunjuk manual. */
    protected static function newFactory(): ItemFactory
    {
        return ItemFactory::new();
    }
}
