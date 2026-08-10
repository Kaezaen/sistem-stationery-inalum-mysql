<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Item;
use App\Shared\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\DB;

class ItemService
{
    /** @param array<string, mixed> $data */
    public function create(array $data): Item
    {
        return DB::transaction(function () use ($data): Item {
            $this->guardStockBounds($data);

            return Item::create($this->attributes($data));
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Item $item, array $data): Item
    {
        return DB::transaction(function () use ($item, $data): Item {
            $this->guardStockBounds($data, $item);

            $item->update($this->attributes($data));

            return $item->refresh();
        });
    }

    /**
     * Menonaktifkan item, bukan menghapusnya.
     *
     * Item yang pernah dipakai transaksi tidak boleh hilang dari basis data:
     * request dan pembelian lama merujuk padanya, dan laporan historis akan
     * rusak bila referensinya putus. Soft delete menjaga riwayat tetap utuh.
     */
    public function deactivate(Item $item): void
    {
        DB::transaction(function () use ($item): void {
            $item->update(['is_active' => false]);
            $item->delete();
        });
    }

    /**
     * Stok yang masih tersisa membuat penghapusan berbahaya — barangnya masih
     * ada secara fisik di gudang meski catatannya dihapus.
     *
     * @throws BusinessRuleException
     */
    public function guardCanDelete(Item $item): void
    {
        if ($item->stock_quantity > 0) {
            throw new BusinessRuleException(sprintf(
                'Item %s masih memiliki stok %d. Kosongkan stok terlebih dahulu sebelum menonaktifkan.',
                $item->item_code,
                $item->stock_quantity,
            ));
        }

        if ($item->reserved_quantity > 0) {
            throw new BusinessRuleException(sprintf(
                'Item %s sedang direservasi untuk request yang belum diserahkan.',
                $item->item_code,
            ));
        }
    }

    /**
     * min_stock tidak boleh melebihi max_stock.
     *
     * Divalidasi di sini selain di FormRequest karena import massal dan seeder
     * tidak melewati FormRequest sama sekali. Constraint database menjadi
     * lapis terakhir.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws BusinessRuleException
     */
    private function guardStockBounds(array $data, ?Item $existing = null): void
    {
        $min = isset($data['min_stock']) ? (int) $data['min_stock'] : ($existing->min_stock ?? 0);
        $max = isset($data['max_stock']) ? (int) $data['max_stock'] : ($existing->max_stock ?? 0);

        if ($min > $max) {
            throw new BusinessRuleException(
                'Min Stock tidak boleh lebih besar daripada Max Stock.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return array_filter(
            [
                'item_code' => $data['item_code'] ?? null,
                'item_name' => $data['item_name'] ?? null,
                'description' => $data['description'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'uom_id' => $data['uom_id'] ?? null,
                'min_stock' => $data['min_stock'] ?? null,
                'max_stock' => $data['max_stock'] ?? null,
                'remark' => $data['remark'] ?? null,
                'is_active' => $data['is_active'] ?? null,
            ],
            static fn (mixed $v): bool => $v !== null,
        );
    }
}
