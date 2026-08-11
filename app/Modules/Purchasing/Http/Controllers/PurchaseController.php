<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Controllers;

use App\Modules\Catalog\Models\Category;
use App\Modules\Purchasing\Enums\PurchaseStatus;
use App\Modules\Purchasing\Http\Requests\StorePurchaseRequest;
use App\Modules\Purchasing\Http\Requests\UpdatePurchaseRequest;
use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Models\PurchaseItem;
use App\Modules\Purchasing\Services\PurchaseService;
use App\Shared\Exceptions\BusinessRuleException;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Support\PaginatedPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function __construct(private readonly PurchaseService $purchases) {}

    /** Halaman "Data Purchasing Items". */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Purchase::class);

        $status = $request->string('status')->toString();

        $purchases = Purchase::query()
            ->with(['creator:id,name', 'verifier:id,name'])
            ->withCount('items')
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($request->string('search')->toString() !== '', function ($q) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $q->where(function ($sub) use ($term): void {
                    $sub->where('purchase_number', 'like', $term)
                        ->orWhere('supplier_name', 'like', $term);
                });
            })
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Purchases/Index', [
            'purchases' => PaginatedPayload::make($purchases, fn (Purchase $p): array => $this->rowPayload($p)),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $status,
            ],
            'statuses' => $this->statusOptions(),
            'canCreate' => $request->user()?->can('create', Purchase::class) ?? false,
        ]);
    }

    /** Halaman "Purchasing Items" — wireframe 3.9.2. */
    public function create(): Response
    {
        $this->authorize('create', Purchase::class);

        return Inertia::render('Purchases/Create', [
            'categories' => $this->categoryOptions(),
            'today' => now()->toDateString(),
        ]);
    }

    public function store(StorePurchaseRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            $purchase = $this->purchases->create($data, $data['items'], $request->user());

            // Wireframe 3.9.2 hanya menyediakan tombol Simpan — dokumen langsung
            // masuk antrian verifikasi, tidak berhenti sebagai draft.
            $this->purchases->submit($purchase);
        } catch (BusinessRuleException $e) {
            throw $e->toValidationException('items');
        }

        return redirect()
            ->route('purchases.index')
            ->with('success', "Pembelian {$purchase->purchase_number} berhasil disimpan dan menunggu verifikasi.");
    }

    public function show(Request $request, Purchase $purchase): Response
    {
        $this->authorize('view', $purchase);

        return Inertia::render('Purchases/Show', [
            'purchase' => $this->detailPayload($purchase),
            'canEdit' => $request->user()?->can('update', $purchase) ?? false,
        ]);
    }

    public function edit(Purchase $purchase): Response
    {
        $this->authorize('update', $purchase);

        return Inertia::render('Purchases/Edit', [
            'purchase' => $this->detailPayload($purchase),
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function update(UpdatePurchaseRequest $request, Purchase $purchase): RedirectResponse
    {
        $data = $request->validated();
        $wasRejected = $purchase->status === PurchaseStatus::Rejected;

        try {
            $this->purchases->update($purchase, $data, $data['items']);

            // Dokumen yang sebelumnya ditolak otomatis diajukan ulang setelah
            // diperbaiki — alur REJECTED -> PENDING_VERIFICATION pada §7.
            if ($wasRejected) {
                $this->purchases->revise($purchase);
            }
        } catch (BusinessRuleException $e) {
            throw $e->toValidationException('items');
        }

        return redirect()
            ->route('purchases.index')
            ->with('success', "Pembelian {$purchase->purchase_number} berhasil diperbarui.");
    }

    public function submit(Purchase $purchase): RedirectResponse
    {
        $this->authorize('update', $purchase);

        try {
            $this->purchases->submit($purchase);
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('purchases.index')
            ->with('success', 'Pembelian diajukan untuk verifikasi.');
    }

    public function destroy(Purchase $purchase): RedirectResponse
    {
        $this->authorize('update', $purchase);

        if ($purchase->status->hasAffectedStock()) {
            return back()->with(
                'error',
                'Pembelian yang sudah diverifikasi tidak dapat dihapus karena stok sudah bertambah. '
                .'Gunakan penyesuaian stok untuk mengoreksi.',
            );
        }

        $purchase->delete();

        return redirect()
            ->route('purchases.index')
            ->with('success', 'Pembelian dihapus.');
    }

    /** @return array<string, mixed> */
    private function rowPayload(Purchase $purchase): array
    {
        return [
            'id' => $purchase->id,
            'purchase_number' => $purchase->purchase_number,
            'purchase_date' => $purchase->purchase_date?->format('d/m/Y'),
            'supplier_name' => $purchase->supplier_name,
            'creator' => $purchase->creator?->name,
            'verifier' => $purchase->verifier?->name,
            'items_count' => $purchase->items_count,
            'status' => $purchase->status->value,
            'status_label' => $purchase->status->label(),
            'status_tone' => $purchase->status->tone(),
        ];
    }

    /** @return array<string, mixed> */
    private function detailPayload(Purchase $purchase): array
    {
        $purchase->load(['items.item.uom', 'items.item.category', 'creator', 'verifier']);

        return [
            ...$this->rowPayload($purchase),
            'purchase_date_raw' => $purchase->purchase_date?->toDateString(),
            'notes' => $purchase->notes,
            'rejection_notes' => $purchase->rejection_notes,
            'revision_count' => $purchase->revision_count,
            'verification_date' => $purchase->verification_date?->format('d/m/Y H:i'),
            'items' => $purchase->items->map(static fn (PurchaseItem $line): array => [
                'id' => $line->id,
                'item_id' => $line->item_id,
                'item_code' => $line->item->item_code ?? '',
                'item_name' => $line->item->item_name ?? '',
                'category' => $line->item->category->name ?? null,
                'uom' => $line->item->uom->code ?? null,
                'quantity' => $line->quantity,
            ])->all(),
        ];
    }

    /** @return array<int, mixed> */
    private function categoryOptions(): array
    {
        return Category::query()->active()->orderBy('name')->get(['id', 'name'])->all();
    }

    /** @return list<array{value: string, label: string}> */
    private function statusOptions(): array
    {
        return array_map(
            static fn (PurchaseStatus $s): array => ['value' => $s->value, 'label' => $s->label()],
            PurchaseStatus::cases(),
        );
    }
}
