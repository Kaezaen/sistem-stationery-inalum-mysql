<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Controllers;

use App\Modules\Approval\Models\Approval;
use App\Modules\Approval\Services\ApprovalService;
use App\Modules\Purchasing\Enums\PurchaseStatus;
use App\Modules\Purchasing\Http\Requests\RejectPurchaseRequest;
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

/**
 * Verifikasi pembelian oleh PIC Stationery — wireframe 3.10.2 dan 3.10.3.
 */
class PurchaseVerificationController extends Controller
{
    public function __construct(
        private readonly PurchaseService $purchases,
        private readonly ApprovalService $approvals,
    ) {}

    /** Antrian verifikasi dengan tab Pending / Approved / Rejected. */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Purchase::class);

        $tab = $request->string('tab')->toString() ?: 'pending';

        $status = match ($tab) {
            'approved' => PurchaseStatus::Verified,
            'rejected' => PurchaseStatus::Rejected,
            default => PurchaseStatus::PendingVerification,
        };

        $purchases = Purchase::query()
            ->with(['creator:id,name'])
            ->withCount('items')
            ->withStatus($status)
            ->when($request->string('search')->toString() !== '', function ($q) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $q->where(function ($sub) use ($term): void {
                    $sub->where('purchase_number', 'ilike', $term)
                        ->orWhere('supplier_name', 'ilike', $term);
                });
            })
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Purchases/Verify/Index', [
            'purchases' => PaginatedPayload::make($purchases, static fn (Purchase $p): array => [
                'id' => $p->id,
                'purchase_number' => $p->purchase_number,
                'purchase_date' => $p->purchase_date?->format('d/m/Y'),
                'supplier_name' => $p->supplier_name,
                'creator' => $p->creator?->name,
                'items_count' => $p->items_count,
                'status_label' => $p->status->label(),
                'status_tone' => $p->status->tone(),
            ]),
            'tab' => $tab,
            'filters' => ['search' => $request->string('search')->toString()],
        ]);
    }

    /** Detail dokumen beserta riwayat keputusan. */
    public function show(Request $request, Purchase $purchase): Response
    {
        $this->authorize('view', $purchase);

        $purchase->load(['items.item.uom', 'creator']);

        return Inertia::render('Purchases/Verify/Show', [
            'purchase' => [
                'id' => $purchase->id,
                'purchase_number' => $purchase->purchase_number,
                'purchase_date' => $purchase->purchase_date?->format('d/m/Y'),
                'supplier_name' => $purchase->supplier_name,
                'creator' => $purchase->creator?->name,
                'notes' => $purchase->notes,
                'rejection_notes' => $purchase->rejection_notes,
                'status' => $purchase->status->value,
                'status_label' => $purchase->status->label(),
                'status_tone' => $purchase->status->tone(),
                'items' => $purchase->items->map(static fn (PurchaseItem $line): array => [
                    'id' => $line->id,
                    'item_code' => $line->item->item_code ?? '',
                    'item_name' => $line->item->item_name ?? '',
                    'uom' => $line->item->uom->code ?? null,
                    'quantity' => $line->quantity,
                    'current_stock' => $line->item->stock_quantity ?? 0,
                ])->all(),
            ],
            // Ditampilkan sebagai timeline; keputusan yang dianulir tetap muncul
            // agar riwayat penolakan sebelumnya terlihat.
            'history' => $this->approvals->history($purchase)
                ->map(static fn (Approval $a): array => [
                    'id' => $a->id,
                    'decision' => $a->status->value,
                    'decision_label' => $a->status->label(),
                    'approver' => $a->approver?->name,
                    'role' => $a->approver_role,
                    'date' => $a->approval_date?->format('d/m/Y H:i'),
                    'notes' => $a->rejection_notes,
                    'superseded' => $a->is_superseded,
                ])->all(),
            'canVerify' => ($request->user()?->can('verify', $purchase) ?? false)
                && $purchase->status === PurchaseStatus::PendingVerification,
        ]);
    }

    public function verify(Request $request, Purchase $purchase): RedirectResponse
    {
        $this->authorize('verify', $purchase);

        try {
            $this->purchases->verify($purchase, $request->user());
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('purchases.verify.index')
            ->with('success', "Pembelian {$purchase->purchase_number} diverifikasi. Stok telah bertambah.");
    }

    public function reject(RejectPurchaseRequest $request, Purchase $purchase): RedirectResponse
    {
        try {
            $this->purchases->reject($purchase, $request->user(), $request->string('rejection_notes')->toString());
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('purchases.verify.index')
            ->with('success', "Pembelian {$purchase->purchase_number} ditolak.");
    }
}
