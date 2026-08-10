<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Requests\StoreItemRequest;
use App\Modules\Catalog\Http\Requests\UpdateItemRequest;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\Uom;
use App\Modules\Catalog\Services\ItemService;
use App\Shared\Exceptions\BusinessRuleException;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Support\PaginatedPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    public function __construct(private readonly ItemService $items) {}

    /** Halaman "Data List Items" — wireframe 3.8. */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Item::class);

        $search = $request->string('search')->toString();
        $categoryId = $request->integer('category_id');

        $items = Item::query()
            ->with(['category:id,code,name', 'uom:id,code'])
            ->search($search)
            ->when($categoryId > 0, fn ($q) => $q->where('category_id', $categoryId))
            ->when($request->string('status')->toString() === 'active', fn ($q) => $q->active())
            ->when($request->string('status')->toString() === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('item_name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Items/Index', [
            'items' => PaginatedPayload::make($items, fn (Item $item): array => $this->rowPayload($item)),
            'categories' => $this->categoryOptions(),
            'filters' => [
                'search' => $search,
                'category_id' => $categoryId > 0 ? $categoryId : null,
                'status' => $request->string('status')->toString(),
            ],
            'canManage' => $request->user()?->can('create', Item::class) ?? false,
        ]);
    }

    /** Halaman "Add New Items" — wireframe 3.8.2. */
    public function create(): Response
    {
        $this->authorize('create', Item::class);

        return Inertia::render('Items/Create', $this->formOptions());
    }

    public function store(StoreItemRequest $request): RedirectResponse
    {
        try {
            $item = $this->items->create($request->validated());
        } catch (BusinessRuleException $e) {
            throw $e->toValidationException('min_stock');
        }

        return redirect()
            ->route('items.index')
            ->with('success', "Item {$item->item_code} berhasil ditambahkan.");
    }

    public function edit(Item $item): Response
    {
        $this->authorize('update', $item);

        return Inertia::render('Items/Edit', [
            ...$this->formOptions(),
            'item' => [
                'id' => $item->id,
                'item_code' => $item->item_code,
                'item_name' => $item->item_name,
                'description' => $item->description,
                'category_id' => $item->category_id,
                'uom_id' => $item->uom_id,
                'min_stock' => $item->min_stock,
                'max_stock' => $item->max_stock,
                'remark' => $item->remark,
                'is_active' => $item->is_active,
                'stock_quantity' => $item->stock_quantity,
                'reserved_quantity' => $item->reserved_quantity,
            ],
        ]);
    }

    public function update(UpdateItemRequest $request, Item $item): RedirectResponse
    {
        try {
            $this->items->update($item, $request->validated());
        } catch (BusinessRuleException $e) {
            throw $e->toValidationException('min_stock');
        }

        return redirect()
            ->route('items.index')
            ->with('success', "Item {$item->item_code} berhasil diperbarui.");
    }

    public function destroy(Item $item): RedirectResponse
    {
        $this->authorize('delete', $item);

        try {
            $this->items->guardCanDelete($item);
            $this->items->deactivate($item);
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('items.index')
            ->with('success', "Item {$item->item_code} berhasil dinonaktifkan.");
    }

    /** @return array<string, mixed> */
    private function rowPayload(Item $item): array
    {
        return [
            'id' => $item->id,
            'item_code' => $item->item_code,
            'item_name' => $item->item_name,
            'category' => $item->category?->name,
            'uom' => $item->uom?->code,
            'stock_quantity' => $item->stock_quantity,
            'available_quantity' => $item->availableQuantity(),
            'min_stock' => $item->min_stock,
            'max_stock' => $item->max_stock,
            'stock_status' => $item->stockStatus()->value,
            'stock_status_label' => $item->stockStatus()->label(),
            'is_active' => $item->is_active,
        ];
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'categories' => $this->categoryOptions(),
            'uoms' => Uom::query()
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->all(),
        ];
    }

    /** @return array<int, mixed> */
    private function categoryOptions(): array
    {
        return Category::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->all();
    }
}
