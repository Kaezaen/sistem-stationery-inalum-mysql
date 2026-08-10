<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Item;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Inventory\Enums\StockStatusFilter;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Queries\StockLedgerQuery;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Support\PaginatedPayload;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function __construct(private readonly StockLedgerQuery $ledger) {}

    /** Halaman "Data Inventory" — wireframe 3.11.2. */
    public function index(Request $request): Response
    {
        $this->authorizePermission($request, Permission::InventoryView->value);

        $status = $request->string('status')->toString();

        $items = Item::query()
            ->with(['category:id,name', 'uom:id,code'])
            ->search($request->string('search')->toString())
            ->when($request->integer('category_id') > 0,
                fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($status === StockStatusFilter::Under->value,
                fn ($q) => $q->whereColumn('stock_quantity', '<', 'min_stock'))
            ->when($status === StockStatusFilter::Over->value,
                fn ($q) => $q->whereColumn('stock_quantity', '>', 'max_stock'))
            ->when($status === StockStatusFilter::OnHand->value, fn ($q) => $q
                ->whereColumn('stock_quantity', '>=', 'min_stock')
                ->whereColumn('stock_quantity', '<=', 'max_stock'))
            ->orderBy('item_name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Inventory/Index', [
            'items' => PaginatedPayload::make($items, fn (Item $item): array => [
                'id' => $item->id,
                'item_code' => $item->item_code,
                'item_name' => $item->item_name,
                'uom' => $item->uom?->code,
                'category' => $item->category?->name,
                'stock_quantity' => $item->stock_quantity,
                'reserved_quantity' => $item->reserved_quantity,
                'available_quantity' => $item->availableQuantity(),
                'min_stock' => $item->min_stock,
                'max_stock' => $item->max_stock,
                'stock_status' => $item->stockStatus()->value,
                'stock_status_label' => $item->stockStatus()->label(),
            ]),
            'categories' => Category::query()->active()->orderBy('name')->get(['id', 'name'])->all(),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'category_id' => $request->integer('category_id') ?: null,
                'status' => $status,
            ],
        ]);
    }

    /** Kartu stok — riwayat pergerakan satu item. */
    public function show(Request $request, Item $item): Response
    {
        $this->authorizePermission($request, Permission::InventoryView->value);

        $transactions = $this->ledger->forItem($item);

        return Inertia::render('Inventory/Show', [
            'item' => [
                'id' => $item->id,
                'item_code' => $item->item_code,
                'item_name' => $item->item_name,
                'uom' => $item->uom?->code,
                'stock_quantity' => $item->stock_quantity,
                'reserved_quantity' => $item->reserved_quantity,
                'available_quantity' => $item->availableQuantity(),
                'min_stock' => $item->min_stock,
                'max_stock' => $item->max_stock,
                'stock_status_label' => $item->stockStatus()->label(),
                'stock_status' => $item->stockStatus()->value,
            ],
            'transactions' => PaginatedPayload::make(
                $transactions,
                fn (InventoryTransaction $tx): array => [
                    'id' => $tx->id,
                    'date' => $tx->transaction_date?->format('d/m/Y H:i'),
                    'type' => $tx->transaction_type->value,
                    'type_label' => $tx->transaction_type->label(),
                    'quantity' => $tx->quantity,
                    'net_change' => $tx->netChange(),
                    'balance_before' => $tx->quantity_before,
                    'balance_after' => $tx->quantity_after,
                    'performed_by' => $tx->performer?->name,
                    'reason' => $tx->adjustment_reason,
                ],
            ),
        ]);
    }
}
