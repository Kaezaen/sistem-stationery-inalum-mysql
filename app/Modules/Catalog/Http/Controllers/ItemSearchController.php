<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Models\Item;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pencarian item untuk pemilih item — wireframe 3.9.3 "Cari Item".
 *
 * Berada di modul Catalog, bukan Purchasing, karena pemilih yang sama akan
 * dipakai ulang oleh layar Request Items pada Fase 5.
 */
class ItemSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Item::class);

        $items = Item::query()
            ->with(['category:id,name', 'uom:id,code'])
            ->active()
            ->search($request->string('q')->toString())
            ->when($request->integer('category_id') > 0,
                fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->orderBy('item_name')
            ->limit(25)
            ->get();

        return response()->json([
            'data' => $items->map(static fn (Item $item): array => [
                'id' => $item->id,
                'item_code' => $item->item_code,
                'item_name' => $item->item_name,
                'category' => $item->category?->name,
                'uom' => $item->uom?->code,
                'stock_quantity' => $item->stock_quantity,
                'available_quantity' => $item->availableQuantity(),
            ])->all(),
        ]);
    }
}
